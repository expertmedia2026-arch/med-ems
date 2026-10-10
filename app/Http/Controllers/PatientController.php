<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PortalInvitationService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $patients = Patient::search($request->query('q'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('patients.index', compact('patients'));
    }

    public function create()
    {
        return view('patients.create');
    }

    public function store(Request $request, BillingService $billing, PortalInvitationService $portal)
    {
        $patient = Patient::create($this->validated($request));

        // Registration fee goes to the cashier
        $invoice = $billing->registrationInvoiceFor($patient);

        $message = "Patient registered: {$patient->hospital_number}.";

        if ($invoice) {
            $message .= ' Registration fee of ₦'.number_format((float) $invoice->total).' is waiting at the cashier.';
        }

        $flash = [];

        // Patients with an email get a verify-and-set-password invitation straight away
        if (filled($patient->email)) {
            $result = $portal->send($patient);

            if ($result['ok']) {
                $message .= ' '.$result['message'];
                $flash['invite_link'] = $result['link'];
            } else {
                $flash['error'] = $result['message'];
            }
        }

        return redirect()->route('patients.show', $patient)->with(['success' => $message] + $flash);
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'visits' => fn ($q) => $q->latest('checked_in_at')->with(['doctor', 'consultationType.department']),
            'appointments' => fn ($q) => $q->latest('scheduled_at')->with('doctor'),
        ]);

        return view('patients.show', [
            'patient' => $patient,
            'activeVisit' => $patient->currentVisit(),
            'doctors' => User::ofRole(Role::Doctor)->where('is_active', true)->with('department')->orderBy('name')->get(),
            'departments' => Department::whereHas('consultationTypes', fn ($query) => $query->active())
                ->orderBy('name')->get(),
            'consultationTypes' => ConsultationType::active()->orderBy('name')->get(),
            'portalUser' => User::where('patient_id', $patient->id)->first(),
            'pendingInvite' => $patient->invitations()->pending()->latest()->first(),
        ]);
    }

    public function card(Patient $patient)
    {
        abort_unless($patient->registration_paid_at, 403, 'The patient card is available after the registration fee has been paid.');

        return view('patients.card', [
            'patient' => $patient,
            'hospital' => HospitalSetting::current(),
        ]);
    }

    // public function store(Request $request)
    // {
    //     $patient = Patient::create($this->validated($request));

    //     return redirect()
    //         ->route('patients.show', $patient)
    //         ->with('success', "Patient registered: {$patient->hospital_number}");
    // }

    // public function show(Patient $patient)
    // {
    //     $patient->load([
    //         'visits'       => fn ($q) => $q->latest('checked_in_at')->with('doctor'),
    //         'appointments' => fn ($q) => $q->latest('scheduled_at')->with('doctor'),
    //     ]);

    //     return view('patients.show', [
    //         'patient'     => $patient,
    //         'activeVisit' => $patient->currentVisit(),
    //         'doctors'     => User::ofRole(Role::Doctor)->orderBy('name')->get(),
    //     ]);
    // }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        // $patient->update($this->validated($request));
        $patient->update($this->validated($request) + ['dob_estimated' => false]);

        return redirect()->route('patients.show', $patient)->with('success', 'Patient updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'blood_group' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'genotype' => ['nullable', 'in:AA,AS,SS,AC,SC'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'is_nhis' => ['sometimes', 'boolean'],
            'nhis_number' => ['nullable', 'required_if:is_nhis,1', 'string', 'max:50'],
        ]);

        $data['is_nhis'] = $request->boolean('is_nhis');
        $data['nhis_number'] = $data['is_nhis'] ? ($data['nhis_number'] ?? null) : null;

        return $data;
    }
}
