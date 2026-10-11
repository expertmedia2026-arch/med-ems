<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDetailsRequest;
use App\Http\Requests\PatientIndexRequest;
use App\Http\Requests\WalkInCheckInRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Http\Resources\Api\V1\PatientProfileResource;
use App\Http\Resources\Api\V1\PatientSummaryResource;
use App\Http\Resources\Api\V1\PatientVisitResource;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\LabAttachment;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\PatientRegistrationService;
use App\Services\PatientService;
use App\Services\PortalDashboardService;
use App\Services\PortalInvitationService;
use App\Services\PortalVisitService;
use App\Services\VisitCheckInService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    public function dashboard(Request $request, PortalDashboardService $dashboard): array
    {
        $patient = $this->patient($request);
        $data = $dashboard->data($patient);
        $data['lastVisit']?->setAttribute('patient_details_visible', true);

        return [
            'patient' => new PatientProfileResource($patient),
            'outstanding_balance' => $data['outstanding'],
            'unpaid_invoice_count' => $data['unpaidInvoices'],
            'next_appointment' => $data['nextAppointment'] ? new AppointmentResource($data['nextAppointment']->loadMissing('doctor.department', 'labTests')) : null,
            'last_visit' => $data['lastVisit'] ? new PatientVisitResource($data['lastVisit']) : null,
        ];
    }

    /** Return card details with HTML rendered from the same Blade view used by web printing. */
    public function card(Patient $patient, PatientService $patients): array
    {
        $data = $patients->cardData($patient);
        $hospital = $data['hospital'];
        $patient = $data['patient'];
        $hospitalDetails = collect([$hospital->address, $hospital->phone, $hospital->email, $hospital->website])
            ->filter()
            ->implode(' · ');

        return [
            'patient' => new PatientProfileResource($patient),
            'hospital' => [
                'name' => $hospital->name,
                'address' => $hospital->address,
                'phone' => $hospital->phone,
                'email' => $hospital->email,
                'website' => $hospital->website,
                'logo_url' => $hospital->logo_url,
                'receipt_footer' => $hospital->receipt_footer,
            ],
            'card' => [
                'title' => 'Patient identification card',
                'hospital' => [
                    'name' => $hospital->name,
                    'details' => $hospitalDetails,
                    'logo_url' => $hospital->logo_url,
                    'logo_fallback' => '+',
                ],
                'patient' => [
                    'full_name' => $patient->full_name,
                    'hospital_number' => $patient->hospital_number,
                    'age' => $patient->age,
                    'age_label' => $patient->age !== null ? $patient->age.' years' : '—',
                    'sex' => ucfirst($patient->gender),
                    'area' => $data['area'] ?: '—',
                    'is_nhis' => (bool) $patient->is_nhis,
                    'nhis_number' => $patient->is_nhis && $patient->nhis_number ? $patient->nhis_number : null,
                ],
                'footer_message' => 'Present this card at every visit',
                'issued_at' => $patient->registration_paid_at->toIso8601String(),
                'issued_label' => 'Issued '.$patient->registration_paid_at->format('d M Y'),
                'print_button_label' => 'Print patient card',
                'back_to_patient_url' => route('patients.show', $patient),
                'print_instructions' => 'Print at actual size (100%) and cut along the card edge.',
            ],
            'printable' => [
                'content_type' => 'text/html; charset=UTF-8',
                'html' => view('patients.card', $data)->render(),
            ],
        ];
    }

    public function index(PatientIndexRequest $request, PatientService $patients)
    {
        return PatientSummaryResource::collection($patients->index($request->validated('q')));
    }

    public function store(PatientDetailsRequest $request, PatientRegistrationService $registration, PortalInvitationService $portal): array
    {
        $result = $registration->register($request->validated());
        $patient = $result['patient'];
        $invitation = filled($patient->email) ? $portal->send($patient) : null;

        return [
            'patient' => new PatientProfileResource($patient),
            'invoice' => $result['invoice'] ? [
                'id' => $result['invoice']->id,
                'invoice_number' => $result['invoice']->invoice_number,
                'total' => (float) $result['invoice']->total,
                'status' => $result['invoice']->status->value,
            ] : null,
            'portal_invitation' => $invitation ? [
                'sent' => $invitation['ok'],
                'message' => $invitation['message'],
            ] : null,
        ];
    }

    public function staffShow(Patient $patient, PatientService $patients): array
    {
        $data = $patients->showData($patient);

        return [
            'patient' => new PatientProfileResource($data['patient']),
            'active_visit' => $data['activeVisit'] ? new VisitResource($data['activeVisit']) : null,
            'visits' => VisitResource::collection($data['patient']->visits),
            'appointments' => AppointmentResource::collection($data['patient']->appointments),
            'portal_user' => $data['portalUser'] ? ['id' => $data['portalUser']->id, 'email' => $data['portalUser']->email] : null,
        ];
    }

    public function update(PatientDetailsRequest $request, Patient $patient, PatientService $patients): PatientProfileResource
    {
        return new PatientProfileResource($patients->update($patient, $request->validated()));
    }

    public function walkIn(WalkInCheckInRequest $request, Patient $patient, VisitCheckInService $checkIn): VisitResource
    {
        $visit = $checkIn->walkIn($patient, $request->validated());
        abort_unless($visit, 422, 'This patient already has an active visit.');

        return new VisitResource($visit->load(['patient', 'department', 'consultationType']));
    }

    public function invite(Patient $patient, PortalInvitationService $portal): array
    {
        abort_unless($patient->email, 422, 'Add an email address before inviting the patient to the portal.');
        $result = $portal->send($patient);

        abort_unless($result['ok'], 422, $result['message']);

        return ['message' => $result['message']];
    }

    public function profile(Request $request): PatientProfileResource
    {
        return new PatientProfileResource($this->patient($request));
    }

    public function visits(Request $request, PortalVisitService $visits): AnonymousResourceCollection
    {
        return PatientVisitResource::collection($visits->index($this->patient($request)));
    }

    public function showVisit(Request $request, Visit $visit, PortalVisitService $visits): PatientVisitResource
    {
        $result = $visits->showForPatient($visit, $this->patient($request)->id);
        $result['visit']->setAttribute('patient_details_visible', ! $result['inProgress']);

        return new PatientVisitResource($result['visit']);
    }

    public function attachment(Request $request, LabAttachment $attachment, PortalVisitService $visits)
    {
        abort_unless($visits->attachmentVisibleToPatient($attachment, $this->patient($request)->id), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function patient(Request $request): Patient
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'A patient account is required to view patient records.');

        return $patient;
    }
}
