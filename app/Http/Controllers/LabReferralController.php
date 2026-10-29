<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\LabReferralFormRequest;
use App\Http\Requests\StoreLabReferralRequest;
use App\Models\Visit;
use App\Services\LabReferralService;

/**
 * Coordinates walk-in laboratory referral forms and lifecycle actions with LabReferralService.
 */
class LabReferralController extends Controller
{
    /**
     * Uses validated patient context to load authorized referral form data and render the entry page.
     */
    public function create(LabReferralFormRequest $request, LabReferralService $referrals)
    {
        return view('lab.referrals.create', $referrals->createFormData($request->validated('patient'), $request->user()));
    }

    /**
     * Creates a validated referral, returning possible patient matches or routing the user to the appropriate page.
     */
    public function store(StoreLabReferralRequest $request, LabReferralService $referrals)
    {
        $data = $request->validated();
        $result = $referrals->create($data, $request->user()->id, $request->boolean('confirm_new'), $request->user());

        if ($result['matches']->isNotEmpty()) {
            return back()->withInput()->with('possible_matches', $result['matches']->map(fn ($patient) => [
                'hospital_number' => $patient->hospital_number,
                'name' => $patient->full_name,
                'gender' => ucfirst($patient->gender),
                'age' => $patient->age,
                'phone' => $patient->phone,
            ])->values()->all());
        }

        $visit = $result['visit'];
        $patient = $visit->patient;
        $total = number_format($result['total'] ?? 0, 2);
        $message = "Request created for {$patient->full_name}"
            .($result['is_new'] ? " (lab number {$patient->hospital_number})" : '')
            .". Send them to the billing desk to pay ₦{$total}.";

        // Front desk has no access to the lab pages, so send them back to the patient
        return $request->user()->hasRole(Role::Receptionist)
            ? redirect()->route('patients.show', $patient)->with('success', $message)
            : redirect()->route('lab.index')->with('success', $message);
    }

    /**
     * Cancels an unpaid referral when permitted by the service, otherwise returns the blocking reason.
     */
    public function destroy(\Illuminate\Http\Request $request, Visit $visit, LabReferralService $referrals)
    {
        if (! $referrals->cancel($visit, $request->user())) {
            return back()->with('error', 'This request cannot be cancelled after payment has been taken or a result entered.');
        }

        return redirect()->route('lab.index')->with('success', 'Referral request cancelled.');
    }
}
