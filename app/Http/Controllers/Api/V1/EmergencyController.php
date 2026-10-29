<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyCheckInRequest;
use App\Http\Requests\ReceiveMortuaryCaseRequest;
use App\Http\Requests\StoreEmergencyProgressNoteRequest;
use App\Http\Requests\UnidentifiedEmergencyCheckInRequest;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\HospitalSetting;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\EmergencyProgressNoteService;
use App\Services\EmergencyService;
use App\Services\HospitalDeathCertificateService;
use App\Services\MortuaryCaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmergencyController extends Controller
{
    public function index(Request $request, EmergencyService $emergency): array
    {
        abort_unless($request->user()->canAccessEmergency(), Response::HTTP_FORBIDDEN);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['q'] ?? '');
        $patients = $search === '' ? collect() : Patient::query()
            ->where('identity_status', '!=', 'external_mortuary')
            ->search($search)
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'hospital_number' => $patient->hospital_number,
                'name' => $patient->full_name,
                'phone' => $patient->phone,
                'identity_status' => $patient->identity_status,
            ])
            ->values();

        return [
            'visits' => $emergency->queue()->through(
                fn (Visit $visit) => (new VisitResource($visit))->resolve($request),
            ),
            'mortuary_handoffs' => $emergency->mortuaryHandoffs()->map(fn (MortuaryCase $case) => $this->mortuaryCaseData($case)),
            'brought_in_dead_cases' => $emergency->broughtInDeadCases()->map(fn (MortuaryCase $case) => $this->mortuaryCaseData($case)),
            'patients' => $patients,
        ];
    }

    public function store(EmergencyCheckInRequest $request, Patient $patient, EmergencyService $emergency): Response
    {
        $visit = $emergency->checkIn($patient, $request->validated(), $request->user());
        abort_if($visit === null, Response::HTTP_CONFLICT, 'This patient already has an active visit or inpatient admission.');

        return $this->visitResponse($visit, Response::HTTP_CREATED);
    }

    public function storeUnidentified(UnidentifiedEmergencyCheckInRequest $request, EmergencyService $emergency): Response
    {
        return $this->visitResponse(
            $emergency->checkInUnidentified($request->validated(), $request->user()),
            Response::HTTP_CREATED,
        );
    }

    public function confirmBroughtInDead(Request $request, Visit $visit, MortuaryCaseService $mortuary): Response
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

        return response()->json([
            'data' => $this->mortuaryCaseData($case),
        ], Response::HTTP_CREATED);
    }

    public function issueBroughtInDeadCertificate(Request $request, Visit $visit, HospitalDeathCertificateService $certificates): array
    {
        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:100'],
            'certified_cause' => ['required', 'string', 'min:2', 'max:5000'],
            'other_conditions' => ['nullable', 'string', 'max:5000'],
        ]);
        $case = $certificates->issueForBroughtInDead($visit, $data, $request->user());

        return [
            'certificate_number' => $case->death_certificate_number,
            'issued_at' => $case->death_certificate_issued_at?->toIso8601String(),
        ];
    }

    public function downloadBroughtInDeadCertificate(Request $request, Visit $visit): Response
    {
        abort_unless($request->user()->hasRole('admin')
            || ($request->user()->hasRole('doctor') && $request->user()->canAccessEmergency()), Response::HTTP_FORBIDDEN);
        $visit->loadMissing(['patient', 'department', 'mortuaryCase.deathCertificateIssuedBy']);
        $case = $visit->mortuaryCase;
        abort_unless($visit->is_emergency && $visit->is_brought_in_dead && $case?->death_certificate_issued_at, Response::HTTP_NOT_FOUND);

        return Pdf::loadView('admissions.death-certificate-pdf', [
            'admission' => null,
            'visit' => $visit,
            'case' => $case,
            'hospital' => HospitalSetting::current(),
        ])->setPaper('a4')->download('death-certificate-'.$case->death_certificate_number.'.pdf');
    }

    public function addProgressNote(StoreEmergencyProgressNoteRequest $request, Visit $visit, EmergencyProgressNoteService $notes): array
    {
        $note = $notes->add($visit, $request->validated('note'), $request->user());
        $note->load('author');

        return [
            'id' => $note->id,
            'type' => $note->note_type,
            'note' => $note->note,
            'author' => $note->author?->name,
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    public function receiveMortuaryCase(ReceiveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary): array
    {
        $case = $mortuary->receive($mortuaryCase, $request->validated(), $request->user());
        abort_if($case->status === 'awaiting_transfer', Response::HTTP_PAYMENT_REQUIRED, 'The mortuary intake fee must be paid before custody can be accepted.');

        return $this->mortuaryCaseData($case);
    }

    private function visitResponse(Visit $visit, int $status): Response
    {
        $visit->load([
            'patient',
            'doctor',
            'department',
            'vitals',
            'emergencyObservations.recorder',
            'emergencyProgressNotes.author',
        ]);

        return (new VisitResource($visit))->response()->setStatusCode($status);
    }

    /**
     * @return array<string, mixed>
     */
    private function mortuaryCaseData(MortuaryCase $case): array
    {
        $case->loadMissing(['patient', 'visit.department']);

        return [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'status' => $case->status,
            'source' => $case->source,
            'cause_of_death' => $case->cause_of_death,
            'release_route' => $case->release_route,
            'deceased_at' => $case->deceased_at?->toIso8601String(),
            'declared_at' => $case->declared_at?->toIso8601String(),
            'patient' => $case->patient ? [
                'id' => $case->patient->id,
                'hospital_number' => $case->patient->hospital_number,
                'name' => $case->patient->full_name,
            ] : null,
            'visit' => $case->visit ? [
                'id' => $case->visit->id,
                'department' => $case->visit->department?->name,
            ] : null,
        ];
    }
}
