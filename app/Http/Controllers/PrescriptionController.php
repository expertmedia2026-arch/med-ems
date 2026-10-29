<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrescriptionRequest;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\PrescriptionService;
use Illuminate\Http\Request;

/**
 * Checks visit ownership and delegates prescribing and pharmacy-order actions to PrescriptionService.
 */
class PrescriptionController extends Controller
{
    /**
     * Confirms the visit is open for the user, submits validated prescription details, and reports service errors or stock status.
     */
    public function store(StorePrescriptionRequest $request, Visit $visit, PrescriptionService $prescriptions)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $result = $prescriptions->prescribe($visit, $request->validated(), $request->user()->id);

        if ($result['error']) {
            $field = str_starts_with($result['error'], 'Allergy alert:') ? 'drug_id' : 'quantity';

            return back()->withInput()->withErrors([$field => $result['error']]);
        }

        $message = $result['shortage'] > 0
            ? "{$result['reserved_quantity']} of {$request->validated('quantity')} {$result['drug']->name} unit(s) reserved; {$result['shortage']} unavailable. Arrange an alternative or external supply for the shortage."
            : "{$result['drug']->name} added to the prescription and reserved.";

        return back()->with('success', $message);
    }

    /**
     * Requires an open visit, places the pharmacy order, and reports an empty order or successful queue handoff.
     */
    public function placeOrder(Request $request, Visit $visit, PrescriptionService $prescriptions)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $count = $prescriptions->placeOrder($visit, $request->user()->id);
        if ($count === 0) {
            return back()->with('error', 'Add at least one prescription before placing the pharmacy order.');
        }

        $visit->loadMissing('patient');
        app(\App\Services\WorkflowNotificationService::class)->pharmacy(
            $visit,
            "A prescription order for {$visit->patient->full_name} ({$visit->patient->hospital_number}) is ready in the pharmacy queue.",
        );

        return back()->with('success', $count === 1
            ? 'Prescription order sent to the pharmacy queue.'
            : "{$count} prescription items sent to the pharmacy queue.");
    }

    /**
     * Restricts removal to an open visit and an unqueued, unpaid prescription before delegating deletion.
     */
    public function destroy(Request $request, Visit $visit, Prescription $prescription, PrescriptionService $prescriptions)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        if ($prescription->pharmacy_ordered_at) {
            return back()->with('error', 'This prescription is already in the pharmacy queue and cannot be removed here.');
        }

        if (! $prescriptions->remove($visit, $prescription)) {
            return back()->with('error', 'This drug has already been paid for and cannot be removed.');
        }

        return back()->with('success', 'Prescription removed.');
    }
}
