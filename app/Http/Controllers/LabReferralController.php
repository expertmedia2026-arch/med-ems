<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LabReferralController extends Controller
{
    public function create(Request $request)
    {
        return view('lab.referrals.create', [
            'patients' => Patient::orderBy('last_name')->limit(500)
                ->get(['id', 'hospital_number', 'first_name', 'last_name']),
            'tests'    => LabTest::active()->orderBy('name')->get(),
            'selected' => $request->query('patient'),
        ]);
    }

    public function store(Request $request, BillingService $billing)
    {
        $data = $request->validate([
            'hospital_number' => ['required', 'string', 'max:100'],
            'referred_from'   => ['required', 'string', 'max:150'],
            'referred_by'     => ['nullable', 'string', 'max:150'],
            'complaint'       => ['nullable', 'string', 'max:500'],
            'lab_test_ids'    => ['required', 'array', 'min:1'],
            'lab_test_ids.*'  => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ], [
            'referred_from.required' => 'Enter the hospital the patient was referred from.',
            'lab_test_ids.required'  => 'Select at least one test.',
        ]);

        $patient = $this->findPatient($data['hospital_number']);

        $visit = DB::transaction(function () use ($data, $patient, $request, $billing) {
            $visit = $patient->visits()->create([
                'type'          => 'lab_only',
                'status'        => VisitStatus::LabOnly,
                'complaint'     => $data['complaint'] ?? null,
                'referred_from' => $data['referred_from'],
                'referred_by'   => $data['referred_by'] ?? null,
            ]);

            foreach (array_unique($data['lab_test_ids']) as $testId) {
                $visit->labRequests()->create([
                    'lab_test_id'  => $testId,
                    'requested_by' => $request->user()->id,
                ]);
            }

            $billing->syncFor($visit);

            return $visit;
        });

        $total   = number_format((float) $visit->invoice()->value('total'), 2);
        $message = "Request created for {$patient->full_name}. Send them to the cashier to pay ₦{$total}.";

        // Front desk has no access to the lab pages, so send them back to the patient
        return $request->user()->hasRole(Role::Receptionist)
            ? redirect()->route('patients.show', $patient)->with('success', $message)
            : redirect()->route('lab.index')->with('success', $message);
    }

    /** Find a patient by hospital number, or by name / phone when the match is unambiguous. */
    private function findPatient(string $input): Patient
    {
        $term = trim($input);

        $exact = Patient::whereRaw('LOWER(hospital_number) = ?', [strtolower($term)])->first();
        if ($exact) {
            return $exact;
        }

        // Every word must match the first name, last name, hospital number or phone
        $matches = Patient::query()
            ->where(function ($q) use ($term) {
                foreach (preg_split('/\s+/', $term) as $part) {
                    $q->where(fn ($w) => $w->where('first_name', 'like', "%{$part}%")
                                        ->orWhere('last_name', 'like', "%{$part}%")
                                        ->orWhere('hospital_number', 'like', "%{$part}%")
                                        ->orWhere('phone', 'like', "%{$part}%"));
                }
            })
            ->orderBy('last_name')
            ->limit(6)
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'hospital_number' => "No patient found for \"{$term}\". Check the spelling or hospital number, or register the patient first.",
            ]);
        }

        throw ValidationException::withMessages([
            'hospital_number' => 'Several patients match: '
                . $matches->map(fn ($p) => "{$p->full_name} ({$p->hospital_number})")->join('; ')
                . '. Enter the exact hospital number.',
        ]);
    }

    public function destroy(Visit $visit)
    {
        abort_unless($visit->isLabOnly(), 404);

        $hasPayment = $visit->invoice?->payments()->exists();
        $hasResults = $visit->labRequests()->completed()->exists();

        if ($hasPayment || $hasResults) {
            return back()->with('error', 'This request cannot be cancelled after payment has been taken or a result entered.');
        }

        $visit->delete(); // invoice and lab requests are removed with it

        return redirect()->route('lab.index')->with('success', 'Referral request cancelled.');
    }
}