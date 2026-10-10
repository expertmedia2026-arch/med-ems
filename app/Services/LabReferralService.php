<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabReferralService
{
    public function __construct(private readonly BillingService $billing) {}

    public function createFormData(?int $selectedPatientId = null): array
    {
        return [
            'patients' => Patient::orderBy('last_name')->limit(500)
                ->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'tests' => LabTest::active()->orderBy('name')->get(),
            'selected' => $selectedPatientId,
        ];
    }

    /** @return array{visit: Visit|null, matches: Collection<int, Patient>, is_new: bool, total: ?float} */
    public function create(array $data, int $requestedBy, bool $confirmNew): array
    {
        $isNew = ($data['mode'] ?? 'existing') === 'new';
        $patient = $isNew ? null : $this->findPatient($data['hospital_number']);
        $matches = $isNew ? $this->possibleDuplicates($data) : collect();

        if ($isNew && $matches->isNotEmpty() && ! $confirmNew) {
            return ['visit' => null, 'matches' => $matches, 'is_new' => true, 'total' => null];
        }

        $visit = DB::transaction(function () use ($data, $patient, $requestedBy) {
            $patient ??= Patient::create([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'gender' => $data['gender'],
                'date_of_birth' => now()->subYears((int) $data['age'])->toDateString(),
                'dob_estimated' => true,
                'phone' => $data['phone'] ?? null,
            ]);

            $visit = $patient->visits()->create([
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
                'complaint' => $data['complaint'] ?? null,
                'referred_from' => $data['referred_from'] ?? null,
                'referred_by' => $data['referred_by'] ?? null,
            ]);

            foreach (array_unique($data['lab_test_ids']) as $testId) {
                $visit->labRequests()->create(['lab_test_id' => $testId, 'requested_by' => $requestedBy]);
            }

            $this->billing->syncFor($visit);

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

    public function cancel(Visit $visit): bool
    {
        abort_unless($visit->isLabOnly(), 404);

        if ($visit->invoice?->payments()->exists() || $visit->labRequests()->completed()->exists()) {
            return false;
        }

        $visit->delete();

        return true;
    }

    private function possibleDuplicates(array $data): Collection
    {
        return Patient::query()->where(function ($query) use ($data) {
            $query->where(fn ($name) => $name
                ->whereRaw('LOWER(first_name) = ?', [strtolower(trim($data['first_name']))])
                ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($data['last_name']))]));

            if (! empty($data['phone'])) {
                $query->orWhere('phone', $data['phone']);
            }
        })->limit(5)->get();
    }

    private function findPatient(string $input): Patient
    {
        $term = trim($input);
        $exact = Patient::whereRaw('LOWER(hospital_number) = ?', [strtolower($term)])->first();
        if ($exact) {
            return $exact;
        }

        $matches = Patient::query()->where(function ($query) use ($term) {
            foreach (preg_split('/\s+/', $term) as $part) {
                $query->where(fn ($word) => $word->whereLike('first_name', "%{$part}%")
                    ->orWhereLike('last_name', "%{$part}%")
                    ->orWhereLike('hospital_number', "%{$part}%")
                    ->orWhereLike('phone', "%{$part}%"));
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
