<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Enums\Role;
use App\Models\HospitalSetting;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers laboratory-only visits for existing or newly registered patients.
 *
 * Patient and test lookups use the application's tenant-aware model scopes. Creation keeps the
 * patient, visit, test requests, and invoice synchronization in one transaction.
 */
class LabReferralService
{
    /**
     * Inject shared billing so laboratory-only referrals use the normal invoice synchronization rules.
     */
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Prepare patient/test choices and current registration pricing for a referral form.
     *
     * The optional patient ID preselects an existing patient, and laboratory users see only active
     * tests owned by their department.
     *
     * @return array{
     *     patients: \Illuminate\Database\Eloquent\Collection<int, Patient>,
     *     tests: \Illuminate\Database\Eloquent\Collection<int, LabTest>,
     *     registrationFee: float,
     *     selected: string|null,
     *     selected_patient: Patient|null
     * }
     */
    public function createFormData(?int $selectedPatientId = null, ?User $user = null): array
    {
        $selectedPatient = $selectedPatientId ? Patient::find($selectedPatientId) : null;

        return [
            'patients' => Patient::orderBy('last_name')->limit(500)
                ->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'tests' => LabTest::active()
                ->when($user?->hasRole(Role::Lab), fn ($query) => $query->where('department_id', $user->department_id ?? 0))
                ->with('department:id,name')->orderBy('name')->get(),
            'registrationFee' => (float) HospitalSetting::current()->registration_fee,
            'selected' => $selectedPatient?->hospital_number,
            'selected_patient' => $selectedPatient,
        ];
    }

    /**
     * Create a lab-only visit for an existing patient or register a new patient and create their visit.
     *
     * Laboratory actors must select only tests from their own department. When a proposed new
     * patient resembles existing records, an unconfirmed request returns those matches without
     * creating anything; confirmation permits creation unless the email or phone is already used.
     * Patient, visit, lab requests, and billing are written in one transaction.
     *
     * @param  array<string, mixed>  $data  Validated patient details, selected test IDs, referral notes, and mode.
     * @param  bool  $confirmNew  Confirms creation when likely duplicate patients were found.
     * @return array{visit: Visit|null, matches: Collection<int, Patient>, is_new: bool, total: ?float}
     */
    public function create(array $data, int $requestedBy, bool $confirmNew, ?User $actor = null): array
    {
        if ($actor?->hasRole(Role::Lab)) {
            abort_if(! $actor->department_id, 403, 'Assign this staff member to a department first.');
            $validCount = LabTest::active()->where('department_id', $actor->department_id)
                ->whereIn('id', array_unique($data['lab_test_ids']))->count();
            abort_unless($validCount === count(array_unique($data['lab_test_ids'])), 403, 'Select services owned by your department.');
        }

        $isNew = ($data['mode'] ?? 'existing') === 'new';
        $patient = $isNew ? null : $this->findPatient($data['hospital_number']);
        $matches = $isNew ? $this->possibleDuplicates($data) : collect();

        if ($isNew && $matches->isNotEmpty() && ! $confirmNew) {
            return ['visit' => null, 'matches' => $matches, 'is_new' => true, 'total' => null];
        }

        if ($isNew) {
            $contactConflict = Patient::query()
                ->when(filled($data['phone'] ?? null), fn ($query) => $query->orWhere('phone', trim($data['phone'])))
                ->when(filled($data['email'] ?? null), fn ($query) => $query->orWhereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))]))
                ->exists();

            if ($contactConflict) {
                throw ValidationException::withMessages([
                    'email' => 'That email address or phone number already belongs to a registered patient. Switch to Existing patient and use their record.',
                ]);
            }
        }

        $visit = DB::transaction(function () use ($data, $patient, $requestedBy, $isNew) {
            $patient ??= Patient::create([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'] ?? now()->subYears((int) $data['age'])->toDateString(),
                'dob_estimated' => empty($data['date_of_birth']),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $visit = $patient->visits()->create([
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
                'complaint' => $data['complaint'] ?? null,
                'referred_from' => $data['referred_from'] ?? null,
                'referred_by' => $data['referred_by'] ?? null,
                'email_lab_results' => $isNew,
            ]);

            foreach (array_unique($data['lab_test_ids']) as $testId) {
                $visit->labRequests()->create(['lab_test_id' => $testId, 'requested_by' => $requestedBy]);
            }

            $this->billing->syncFor($visit, includeRegistrationFee: $isNew);

            return $visit;
        });

        $visit->load('patient');

        return [
            'visit' => $visit,
            'matches' => collect(),
            'is_new' => $isNew,
            'total' => (float) $visit->invoice()->value('total'),
        ];
    }

    /**
     * Cancel an unpaid, unfinished laboratory-only visit when the caller can manage its requests.
     *
     * A false result means a payment exists or at least one test has completed; such visits are
     * preserved. On success, deleting the visit removes its dependent referral records.
     */
    public function cancel(Visit $visit, ?User $actor = null): bool
    {
        abort_unless($visit->isLabOnly(), 404);
        if ($actor?->hasRole(Role::Lab)) {
            abort_unless($actor->department_id && ! $visit->labRequests()->whereDoesntHave('test', fn ($query) => $query->where('department_id', $actor->department_id))->exists(), 403);
        }

        if ($visit->invoice?->payments()->exists() || $visit->labRequests()->completed()->exists()) {
            return false;
        }

        $visit->delete();

        return true;
    }

    /**
     * Find up to five patients whose names, phone, or email resemble proposed new-patient details.
     *
     * Name matching is case-insensitive; optional phone and email values are included as alternate
     * match keys to prompt the caller to confirm a likely duplicate.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Patient>
     */
    private function possibleDuplicates(array $data): Collection
    {
        return Patient::query()->where(function ($query) use ($data) {
            $query->where(fn ($name) => $name
                ->whereRaw('LOWER(first_name) = ?', [strtolower(trim($data['first_name']))])
                ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($data['last_name']))]));

            if (! empty($data['phone'])) {
                $query->orWhere('phone', $data['phone']);
            }
            if (! empty($data['email'])) {
                $query->orWhere('email', strtolower(trim($data['email'])));
            }
        })->limit(5)->get();
    }

    /**
     * Resolve an existing patient by exact hospital number/email or an unambiguous text search.
     *
     * Ambiguous and empty searches raise field-level validation errors with guidance for selecting
     * an exact hospital number or switching to new-patient registration.
     */
    private function findPatient(string $input): Patient
    {
        $term = trim($input);
        $exact = Patient::whereRaw('LOWER(hospital_number) = ?', [strtolower($term)])->first();
        $exact ??= Patient::whereRaw('LOWER(email) = ?', [strtolower($term)])->first();
        if ($exact) {
            return $exact;
        }

        $matches = Patient::query()->where(function ($query) use ($term) {
            foreach (preg_split('/\s+/', $term) as $part) {
                $query->where(fn ($word) => $word->whereLike('first_name', "%{$part}%")
                    ->orWhereLike('last_name', "%{$part}%")
                    ->orWhereLike('hospital_number', "%{$part}%")
                    ->orWhereLike('phone', "%{$part}%")
                    ->orWhereLike('email', "%{$part}%"));
            }
        })->orderBy('last_name')->limit(6)->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages(['hospital_number' => "No patient found for \"{$term}\". Check the spelling, or switch to New client."]);
        }

        throw ValidationException::withMessages([
            'hospital_number' => 'Several patients match: '.$matches->map(fn ($patient) => "{$patient->full_name} ({$patient->hospital_number})")->join('; ').'. Enter the exact hospital number.',
        ]);
    }
}
