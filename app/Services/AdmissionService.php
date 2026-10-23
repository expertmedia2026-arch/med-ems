<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Admission;
use App\Models\AdmissionNote;
use App\Models\AdmissionVital;
use App\Models\Department;
use App\Models\Drug;
use App\Models\LabRequest;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly MedicationAdministrationService $medicationAdministration,
        private readonly PrescriptionService $prescriptions,
        private readonly MortuaryCaseService $mortuaryCases,
    ) {}

    public function wardDepartments()
    {
        return Department::query()->where('has_ward', true)->orderBy('name')->get();
    }

    public function indexData(User $viewer, ?string $search = null): array
    {
        $this->assertStaffDepartment($viewer);
        $base = Admission::query()
            ->with(['patient', 'department', 'attendingDoctor'])
            ->when($viewer->hasRole(Role::Doctor), fn ($query) => $query->where(fn ($scope) => $scope
                ->where('attending_doctor_id', $viewer->id)
                ->when($viewer->department_id, fn ($scope) => $scope->orWhere('department_id', $viewer->department_id))))
            ->when($viewer->hasRole(Role::Nurse), fn ($query) => $query->where('department_id', $viewer->department_id))
            ->when($search, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->whereLike('hospital_number', "%{$search}%")
                ->orWhereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")));

        return [
            'activeAdmissions' => (clone $base)->where('status', AdmissionStatus::Active->value)->withCount([
                'visit as pending_result_reviews_count' => fn ($query) => $query->whereHas('labRequests', fn ($labs) => $labs
                    ->where('status', WorkStatus::Completed->value)->whereNull('result_reviewed_at')),
            ])->latest('admitted_at')->paginate(15, ['*'], 'active_page')->withQueryString(),
            'dischargedAdmissions' => (clone $base)->whereIn('status', [AdmissionStatus::Discharged->value, AdmissionStatus::Deceased->value])
                ->with('visit.mortuaryCase')->latest('updated_at')->paginate(15, ['*'], 'discharged_page')->withQueryString(),
            'activeCount' => (clone $base)->where('status', AdmissionStatus::Active->value)->count(),
            'pendingReviewCount' => (clone $base)
                ->whereHas('visit.labRequests', fn ($query) => $query->where('status', WorkStatus::Completed->value)->whereNull('result_reviewed_at'))->count(),
            'search' => $search,
        ];
    }

    public function showData(Admission $admission, User $viewer): array
    {
        $this->assertAdmissionAccess($admission, $viewer);
        $admission->load([
            'patient', 'department', 'attendingDoctor', 'admittedBy', 'dischargedBy',
            'visit.patient', 'visit.doctor', 'visit.department', 'visit.vitals', 'visit.mortuaryCase.declaredBy', 'visit.mortuaryCase.events.actor',
            'visit.prescriptions.drug', 'visit.prescriptions.dispenser',
            'visit.prescriptions.administrations.nurse', 'visit.prescriptions.administrationWaiver',
            'visit.labRequests.test', 'visit.labRequests.processor', 'visit.labRequests.resultReviewer', 'visit.labRequests.attachments',
            'notes.author', 'vitals.recorder',
            'sugarChecks.checker', 'fluidEntries.recorder', 'fluidEntries.prescription.drug', 'oxygenAdministrations.nurse',
            'nebulizerUses.recorder', 'insulinMachineBlocks.starter', 'insulinMachineBlocks.invoiceItem.payment',
            'insulinMachineBlocks.uses.prescription.drug', 'insulinMachineBlocks.uses.opener',
            'damaRecord.witnesses', 'damaRecord.clinician',
        ]);
        $pendingResults = $admission->visit->labRequests
            ->filter(fn (LabRequest $lab) => $lab->status === WorkStatus::Completed && ! $lab->result_reviewed_at);
        $unresolvedResults = $admission->visit->labRequests
            ->filter(fn (LabRequest $lab) => $lab->status !== WorkStatus::Completed || ! $lab->result_reviewed_at);
        $canManage = $viewer->hasRole(Role::Admin) || ($viewer->hasRole(Role::Doctor) && $this->doctorCanAttend($admission, $viewer));
        $canPrescribe = $viewer->hasRole(Role::Doctor) && $this->doctorCanAttend($admission, $viewer);
        $canRecordCare = $viewer->hasRole(Role::Admin)
            || ($viewer->hasRole(Role::Nurse) && (int) $viewer->department_id === (int) $admission->department_id)
            || ($viewer->hasRole(Role::Doctor) && $this->doctorCanAttend($admission, $viewer));

        $drugs = $canPrescribe
            ? Drug::active()->whereRaw('(stock_quantity - reserved_quantity) > 0')->orderBy('name')->get()
            : collect();

        return compact('admission', 'pendingResults', 'unresolvedResults', 'canManage', 'canPrescribe', 'canRecordCare', 'drugs');
    }

    public function admit(Visit $visit, array $data, User $doctor): Admission
    {
        abort_unless($doctor->hasRole(Role::Doctor), 403);

        return DB::transaction(function () use ($visit, $data, $doctor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->isOpenFor($doctor), 403, 'Only the attending doctor can admit a patient from an open consultation.');
            abort_if($visit->admission()->exists(), 422, 'An admission record already exists for this consultation.');

            $patient = Patient::query()->lockForUpdate()->findOrFail($visit->patient_id);
            abort_if($patient->activeAdmission()->exists(), 422, 'This patient already has an active admission.');

            $department = Department::query()->whereKey($data['department_id'])->where('has_ward', true)->firstOrFail();

            $admission = Admission::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'department_id' => $department->id,
                'attending_doctor_id' => $doctor->id,
                'admitted_by' => $doctor->id,
                'status' => AdmissionStatus::Active,
                'admitted_at' => now(),
                'admission_reason' => $data['admission_reason'],
                'admission_diagnosis' => $data['admission_diagnosis'],
                'initial_treatment_plan' => $data['initial_treatment_plan'],
                'expected_discharge_date' => $data['expected_discharge_date'] ?? null,
            ]);
            $admission->update(['admission_number' => 'ADM-'.now()->format('Ymd').'-'.str_pad((string) $admission->id, 6, '0', STR_PAD_LEFT)]);

            $visit->update([
                'diagnosis' => $data['admission_diagnosis'],
                'status' => VisitStatus::Inpatient,
                'disposition' => $visit->is_emergency ? 'admitted' : null,
                'closed_at' => now(),
            ]);
            $visit->prescriptions()
                ->where('status', WorkStatus::Pending->value)
                ->whereNull('pharmacy_ordered_at')
                ->update(['pharmacy_ordered_at' => now()]);
            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);
            $this->billing->syncFor($visit);

            return $admission->refresh();
        });
    }

    public function discharge(Admission $admission, array $data, User $actor): Admission
    {
        $this->assertCanManage($admission, $actor);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Active, 422, 'This admission has already been discharged.');
            $visit = Visit::query()->lockForUpdate()->findOrFail($admission->visit_id);
            abort_unless($visit->status === VisitStatus::Inpatient, 422, 'The linked consultation is not in inpatient status.');

            $this->medicationAdministration->assertReadyForDischarge($visit);

            $unresolvedLabs = $visit->labRequests()
                ->where(fn ($query) => $query->where('status', '!=', WorkStatus::Completed->value)->orWhereNull('result_reviewed_at'))
                ->exists();
            if ($unresolvedLabs && blank($data['pending_results_plan'] ?? null)) {
                throw ValidationException::withMessages([
                    'pending_results_plan' => 'Document who will follow up any outstanding or unreviewed test results before discharge.',
                ]);
            }

            $admission->update([
                'status' => AdmissionStatus::Discharged,
                'discharged_by' => $actor->id,
                'discharged_at' => now(),
                'discharge_diagnosis' => $data['discharge_diagnosis'],
                'discharge_summary' => $data['discharge_summary'],
                'discharge_medications' => $data['discharge_medications'],
                'follow_up_instructions' => $data['follow_up_instructions'],
                'patient_discharge_instructions' => $data['patient_discharge_instructions'],
                'pending_results_plan' => $data['pending_results_plan'] ?? null,
            ]);
            $visit->update([
                'status' => VisitStatus::Completed,
                'closed_at' => now(),
                'discharged_by' => $actor->id,
                'discharged_at' => now(),
            ]);

            return $admission->refresh();
        });
    }

    public function declareDeceased(Admission $admission, array $data, User $actor): MortuaryCase
    {
        $this->assertCanManage($admission, $actor);

        return $this->mortuaryCases->declareInpatientDeath($admission, $data, $actor);
    }

    public function reviewLabResult(Admission $admission, LabRequest $labRequest, array $data, User $doctor): LabRequest
    {
        $this->assertCanManage($admission, $doctor);
        abort_unless($labRequest->visit_id === $admission->visit_id, 404);

        return DB::transaction(function () use ($labRequest, $data, $doctor) {
            $labRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            abort_unless($labRequest->status === WorkStatus::Completed, 422, 'Only a completed laboratory result can be reviewed.');
            abort_if($labRequest->result_reviewed_at, 422, 'This result has already been reviewed.');
            $labRequest->update([
                'result_reviewed_by' => $doctor->id,
                'result_reviewed_at' => now(),
                'result_review_note' => $data['review_note'] ?? null,
            ]);

            return $labRequest->refresh();
        });
    }

    public function addProgressNote(Admission $admission, string $note, User $author): AdmissionNote
    {
        $this->assertAdmissionAccess($admission, $author);
        abort_unless($admission->status === AdmissionStatus::Active, 422, 'Progress notes can only be added during an active admission.');
        abort_unless($author->hasRole(Role::Doctor, Role::Nurse), 403, 'Only the attending doctor or ward nurse can add a clinical progress note.');
        if ($author->hasRole(Role::Doctor)) {
            $this->assertCanManage($admission, $author);
        }

        return $admission->notes()->create([
            'author_id' => $author->id,
            'note_type' => $author->hasRole(Role::Nurse) ? 'nursing' : 'medical',
            'note' => $note,
        ]);
    }

    public function recordVitals(Admission $admission, array $data, User $nurse): AdmissionVital
    {
        abort_unless($nurse->hasRole(Role::Admin, Role::Nurse), 403);
        $this->assertAdmissionAccess($admission, $nurse);

        return DB::transaction(function () use ($admission, $data, $nurse) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Active, 422, 'Vitals can only be recorded during an active admission.');
            abort_unless($admission->visit()->where('status', VisitStatus::Inpatient->value)->exists(), 422, 'This admission is not linked to an active inpatient visit.');
            $this->assertAdmissionAccess($admission, $nurse);

            return $admission->vitals()->create($data + [
                'recorded_by' => $nurse->id,
                'recorded_at' => now(),
            ]);
        });
    }

    /** @return array{drug: Drug|null, error: string|null, reserved_quantity?: int, shortage?: int} */
    public function prescribeMedication(Admission $admission, array $data, User $doctor): array
    {
        abort_unless($doctor->hasRole(Role::Doctor), 403);
        $this->assertCanManage($admission, $doctor);

        return DB::transaction(function () use ($admission, $data, $doctor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Active, 422, 'Medication can only be ordered during an active admission.');
            $visit = Visit::query()->lockForUpdate()->findOrFail($admission->visit_id);
            abort_unless($visit->status === VisitStatus::Inpatient, 422, 'The linked visit is not open for inpatient treatment.');
            $this->assertCanManage($admission, $doctor);

            return $this->prescriptions->prescribe($visit, $data, $doctor->id);
        });
    }

    private function assertAdmissionAccess(Admission $admission, User $viewer): void
    {
        if ($viewer->hasRole(Role::Admin)) {
            return;
        }

        if ($viewer->hasRole(Role::Doctor) && $this->doctorCanAttend($admission, $viewer)) {
            return;
        }

        abort_unless($viewer->hasRole(Role::Nurse) && (int) $viewer->department_id === (int) $admission->department_id,
            403, 'Only the attending doctor, a doctor in this ward department, or a nurse assigned to this ward can access the admission.');
    }

    private function assertCanManage(Admission $admission, User $actor): void
    {
        abort_unless($actor->hasRole(Role::Admin)
            || ($actor->hasRole(Role::Doctor) && $this->doctorCanAttend($admission, $actor)),
            403, 'Only the attending doctor, a doctor in this ward department, or an administrator can manage this admission.');
    }

    private function doctorCanAttend(Admission $admission, User $doctor): bool
    {
        return $admission->attending_doctor_id === $doctor->id
            || ($doctor->department_id && (int) $doctor->department_id === (int) $admission->department_id);
    }

    private function assertStaffDepartment(User $viewer): void
    {
        if ($viewer->hasRole(Role::Nurse)) {
            abort_if(! $viewer->department_id, 403, 'Assign your nurse account to the ward department to view admissions.');
        }
    }
}
