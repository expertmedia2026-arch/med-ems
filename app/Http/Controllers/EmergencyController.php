<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmergencyCheckInRequest;
use App\Http\Requests\ReceiveMortuaryCaseRequest;
use App\Http\Requests\StoreEmergencyProgressNoteRequest;
use App\Http\Requests\UnidentifiedEmergencyCheckInRequest;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Services\EmergencyService;
use App\Services\EmergencyProgressNoteService;
use App\Services\MortuaryCaseService;
use App\Services\HospitalDeathCertificateService;
use App\Models\HospitalSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Coordinates emergency check-in, death, mortuary, and progress-note requests with clinical services.
 */
class EmergencyController extends Controller
{
    /**
     * Checks emergency access, prepares the patient search, and renders service-backed emergency queues.
     */
    public function index(Request $request, EmergencyService $emergency)
    {
        abort_unless($request->user()->canAccessEmergency(), 403);

        $search = trim($request->string('q')->value());
        $patients = $search === '' ? collect() : Patient::query()
            ->where('identity_status', '!=', 'external_mortuary')
            ->search($search)
            ->orderBy('last_name')
            ->limit(10)
            ->get();

        return view('emergency.index', [
            'visits' => $emergency->queue(),
            'mortuaryHandoffs' => $emergency->mortuaryHandoffs(),
            'broughtInDeadCases' => $emergency->broughtInDeadCases(),
            'patients' => $patients,
            'search' => $search,
        ]);
    }

    /**
     * Submits validated patient check-in details and redirects with the appropriate active-visit or death workflow message.
     */
    public function store(EmergencyCheckInRequest $request, Patient $patient, EmergencyService $emergency)
    {
        $visit = $emergency->checkIn($patient, $request->validated(), $request->user());

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit or inpatient admission. Review their record before creating another A&E visit.');
        }

        $message = $visit->is_brought_in_dead
            ? "{$patient->full_name} has an A&E BID file open and is waiting for doctor confirmation. No mortuary transfer is authorized yet."
            : "{$patient->full_name} checked in to Accident & Emergency. Send them for immediate triage.";

        return redirect()->route('emergency.index')->with('success', $message);
    }

    /**
     * Creates an unidentified-patient emergency visit from validated details and returns to the queue.
     */
    public function storeUnidentified(UnidentifiedEmergencyCheckInRequest $request, EmergencyService $emergency)
    {
        $visit = $emergency->checkInUnidentified($request->validated(), $request->user());

        return redirect()->route('emergency.index')->with('success', "Unidentified arrival recorded as {$visit->patient->hospital_number}. Send them for immediate triage.");
    }

    /**
     * Validates death and disposition details, delegates doctor confirmation, and returns to the emergency queue.
     */
    public function confirmBroughtInDead(Request $request, \App\Models\Visit $visit, MortuaryCaseService $mortuary)
    {
        $data = $request->validate([
            'cause_of_death' => ['required', 'string', 'min:2', 'max:5000'],
            'death_clinical_summary' => ['required', 'string', 'min:2', 'max:10000'],
            'disposition' => ['required', 'in:mortuary_transfer,direct_family_release'],
            'approval_confirmed' => ['accepted'],
            'transfer_approval_note' => ['nullable', 'string', 'max:2000'],
            'released_to' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:150'],
            'recipient_relationship' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:100'],
            'recipient_id_reference' => ['nullable', 'string', 'max:120'],
            'release_authority' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:150'],
            'release_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $case = $mortuary->confirmBroughtInDead($visit, $data, $request->user());

        $message = $case->release_route === 'direct_family_release'
            ? 'A&E doctor confirmed the death and documented direct family release.'
            : 'A&E doctor confirmed the death and approved mortuary transfer. The case is awaiting physical receipt.';

        return redirect()->route('emergency.index')->with('success', $message);
    }

    /**
     * Validates certificate details, issues the brought-in-dead certificate, and redirects to its PDF download.
     */
    public function issueBroughtInDeadCertificate(Request $request, \App\Models\Visit $visit, HospitalDeathCertificateService $certificates)
    {
        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:100'],
            'certified_cause' => ['required', 'string', 'min:2', 'max:5000'],
            'other_conditions' => ['nullable', 'string', 'max:5000'],
        ]);
        $case = $certificates->issueForBroughtInDead($visit, $data, $request->user());

        return redirect()->route('emergency.brought-in-dead.certificate.download', $visit)
            ->with('success', 'Hospital death certificate '.$case->death_certificate_number.' issued and recorded.');
    }

    /**
     * Authorizes emergency certificate access and streams a PDF for the issued brought-in-dead certificate.
     */
    public function downloadBroughtInDeadCertificate(Request $request, \App\Models\Visit $visit)
    {
        abort_unless($request->user()->hasRole('admin')
            || ($request->user()->hasRole('doctor') && $request->user()->canAccessEmergency()), 403);
        $visit->loadMissing(['patient', 'department', 'mortuaryCase.deathCertificateIssuedBy']);
        $case = $visit->mortuaryCase;
        abort_unless($visit->is_emergency && $visit->is_brought_in_dead && $case?->death_certificate_issued_at, 404);

        return Pdf::loadView('admissions.death-certificate-pdf', [
            'admission' => null,
            'visit' => $visit,
            'case' => $case,
            'hospital' => HospitalSetting::current(),
        ])->setPaper('a4')->download('death-certificate-'.$case->death_certificate_number.'.pdf');
    }

    /**
     * Records receipt of a validated mortuary handoff and reports when invoice payment still blocks custody.
     */
    public function receiveMortuaryCase(ReceiveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        $case = $mortuary->receive($mortuaryCase, $request->validated(), $request->user());

        if ($case->status === 'awaiting_transfer') {
            return redirect()->route('emergency.index')->with('error', 'The mortuary intake fee must be paid before custody can be accepted.');
        }

        return redirect()->route('emergency.index')->with('success', 'Mortuary handoff received and storage location recorded.');
    }

    /**
     * Adds the validated progress note to the emergency visit and returns to the referring page.
     */
    public function addProgressNote(StoreEmergencyProgressNoteRequest $request, \App\Models\Visit $visit, EmergencyProgressNoteService $notes)
    {
        $notes->add($visit, $request->validated('note'), $request->user());

        return back()->with('success', 'A&E progress note added to the visit record.');
    }
}
