<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Services\BillingService;
use Illuminate\Http\Request;

class TriageController extends Controller
{
    public function index(BillingService $billing)
    {
        $waiting = Visit::with(['patient', 'invoice.items'])
            ->withStatus(VisitStatus::WaitingTriage)
            ->oldest('checked_in_at')
            ->get();

        // Safety net for visits that somehow have no invoice yet
        $waiting->each(function (Visit $visit) use ($billing) {
            if (! $visit->invoice) {
                $billing->invoiceFor($visit);
                $visit->load('invoice.items');
            }
        });

        $done = Visit::with(['patient', 'vitals'])
            ->withStatus(VisitStatus::WaitingDoctor)
            ->today()
            ->latest('updated_at')
            ->get();

        return view('triage.index', compact('waiting', 'done'));
    }

    public function show(Visit $visit, BillingService $billing)
    {
        abort_unless(
            in_array($visit->status, [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor]),
            403,
            'Vitals can no longer be edited for this visit.'
        );

        if (! $billing->consultationPaid($visit)) {
            return $this->unpaid();
        }

        $visit->load(['patient', 'vitals']);

        return view('triage.show', compact('visit'));
    }

    public function store(Request $request, Visit $visit, BillingService $billing)
    {
        abort_unless(
            in_array($visit->status, [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor]),
            403
        );

        if (! $billing->consultationPaid($visit)) {
            return $this->unpaid();
        }

        $data = $request->validate([
            'temperature'      => ['required', 'numeric', 'between:30,45'],
            'pulse'            => ['required', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'spo2'             => ['nullable', 'integer', 'between:50,100'],
            'bp_systolic'      => ['nullable', 'required_with:bp_diastolic', 'integer', 'between:50,300'],
            'bp_diastolic'     => ['nullable', 'required_with:bp_systolic', 'integer', 'between:30,200', 'lt:bp_systolic'],
            'weight'           => ['nullable', 'numeric', 'between:0.5,400'],
            'height'           => ['nullable', 'numeric', 'between:20,250'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'complaint'        => ['nullable', 'string', 'max:500'],
        ]);

        $complaint = $data['complaint'] ?? null;
        unset($data['complaint']);

        $visit->vitals()->updateOrCreate(
            ['visit_id' => $visit->id],
            $data + ['recorded_by' => $request->user()->id]
        );

        $visit->update(array_filter([
            'status'    => VisitStatus::WaitingDoctor,
            'complaint' => $complaint,
        ]));

        return redirect()
            ->route('triage.index')
            ->with('success', "Vitals saved. {$visit->patient->full_name} is now waiting for a doctor.");
    }

    private function unpaid()
    {
        return redirect()
            ->route('triage.index')
            ->with('error', 'The consultation fee has not been paid. Please send the patient to the cashier first.');
    }
}