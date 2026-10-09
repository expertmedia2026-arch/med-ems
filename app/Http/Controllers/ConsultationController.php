<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BillingService;


class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $visits = Visit::with(['patient', 'vitals'])
            ->withStatus(VisitStatus::WaitingDoctor, VisitStatus::InConsultation)
            ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $userId))
            ->oldest('checked_in_at')
            ->get();

        return view('consultations.index', compact('visits'));
    }

    public function start(Request $request, Visit $visit)
    {
        $this->ensureMine($visit, $request);
        abort_unless($visit->status === VisitStatus::WaitingDoctor, 422, 'This patient is not waiting for a doctor.');

        $visit->update([
            'doctor_id'    => $request->user()->id,
            'status'       => VisitStatus::InConsultation,
            'consulted_at' => now(),
        ]);

        return redirect()->route('consultations.show', $visit);
    }

    public function show(Request $request, Visit $visit)
    {
        $this->ensureMine($visit, $request);
        abort_if($visit->status === VisitStatus::WaitingTriage, 404);

        // $visit->load(['patient', 'vitals', 'prescriptions.drug', 'labRequests.test']);
    // $visit->load(['patient', 'vitals', 'prescriptions.drug', 'labRequests.test', 'labRequests.attachments']);
        $visit->load(['patient', 'vitals', 'prescriptions.drug', 'prescriptions.invoiceItem', 'labRequests.test', 'labRequests.attachments', 'labRequests.invoiceItem']);

        return view('consultations.show', [
            'visit'    => $visit,
            'editable' => $visit->isOpenFor($request->user()),
            'history'  => $visit->patient->visits()
                ->with('doctor')
                ->where('id', '!=', $visit->id)
                ->withStatus(VisitStatus::Completed)
                ->latest('checked_in_at')
                ->take(5)
                ->get(),
                'drugs' => Drug::active()->where('stock_quantity', '>', 0)->orderBy('name')->get(),
                'tests' => LabTest::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Visit $visit)
    {
        $this->ensureOpen($visit, $request);
        $visit->update($this->notes($request));

        return back()->with('success', 'Consultation notes saved.');
    }

    public function complete(Request $request, Visit $visit)
    {
        $this->ensureOpen($visit, $request);

        $data = $this->notes($request, diagnosisRequired: true);

        DB::transaction(function () use ($visit, $data) {
            $visit->update($data + [
                'status'    => VisitStatus::Completed,
                'closed_at' => now(),
            ]);

            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);

            app(BillingService::class)->syncFor($visit);

        });

        return redirect()
            ->route('consultations.index')
            ->with('success', "Consultation completed for {$visit->patient->full_name}.");
    }

    private function notes(Request $request, bool $diagnosisRequired = false): array
    {
        return $request->validate([
            'complaint'      => ['nullable', 'string', 'max:500'],
            'diagnosis'      => [$diagnosisRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
        ], ['diagnosis.required' => 'Enter a diagnosis before completing the consultation.']);
    }

    private function ensureMine(Visit $visit, Request $request): void
    {
        abort_unless(
            $visit->doctor_id === null || $visit->doctor_id === $request->user()->id,
            403,
            'This patient is assigned to another doctor.'
        );
    }

    private function ensureOpen(Visit $visit, Request $request): void
    {
        abort_unless($visit->isOpenFor($request->user()), 403, 'This consultation is not open for editing.');
    }

    public function history(Request $request, Visit $visit)
    {
        // Only finished consultations can be viewed as history
        abort_unless($visit->status === VisitStatus::Completed, 404);

        $visit->load([
            'patient',
            'doctor',
            'vitals',
            'prescriptions.drug',
            'prescriptions.dispenser',
            'labRequests.test',
            'labRequests.processor',
            'labRequests.attachments'
        ]);

        // "Back" returns to the consultation the doctor came from
        $backUrl = $request->filled('from')
            ? route('consultations.show', (int) $request->query('from'))
            : route('consultations.index');

        return view('consultations.history', compact('visit', 'backUrl'));
    }

}