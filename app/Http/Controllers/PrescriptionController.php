<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrescriptionRequest;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\PrescriptionService;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function store(StorePrescriptionRequest $request, Visit $visit, PrescriptionService $prescriptions)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $result = $prescriptions->prescribe($visit, $request->validated(), $request->user()->id);

        if ($result['error']) {
            $field = str_starts_with($result['error'], 'Allergy alert:') ? 'drug_id' : 'quantity';

            return back()->withInput()->withErrors([$field => $result['error']]);
        }

        return back()->with('success', "{$result['drug']->name} added to the prescription.");
    }

    public function destroy(Request $request, Visit $visit, Prescription $prescription, PrescriptionService $prescriptions)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        if (! $prescriptions->remove($visit, $prescription)) {
            return back()->with('error', 'This drug has already been paid for and cannot be removed.');
        }

        return back()->with('success', 'Prescription removed.');
    }
}
