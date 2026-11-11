<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\DrugInventoryService;
use App\Services\LabWorkflowService;
use App\Services\MedicationAdministrationService;
use App\Services\PharmacyService;
use App\Services\PrescriptionService;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class FullHospitalWorkflowSeeder extends Seeder
{
    public int $hospitalId = 1;

    private const MARKER = '[FULL_HOSPITAL_DEMO]';

    public function run(
        BillingService $billing,
        DrugInventoryService $inventory,
        LabWorkflowService $laboratory,
        PrescriptionService $prescriptions,
        PharmacyService $pharmacy,
        MedicationAdministrationService $medicationAdministration,
    ): void {
        $hospital = Hospital::query()->find($this->hospitalId);
        if (! $hospital) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} does not exist.");
        }

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set($this->hospitalId);

        try {
            Notification::fake();

            if (Visit::query()->where('clinical_notes', 'like', '%'.self::MARKER.'%')->exists()) {
                $this->command?->info("Full workflow demo already exists for hospital ID {$this->hospitalId}; no changes made.");

                return;
            }

            $resources = $this->resources();

            DB::transaction(function () use (
                $billing,
                $inventory,
                $laboratory,
                $prescriptions,
                $pharmacy,
                $medicationAdministration,
                $resources,
            ): void {
                foreach ($resources['drugs'] as $drug) {
                    if (! $drug->movements()->exists()) {
                        $inventory->recordOpeningBalance($drug);
                    }
                }

                $this->seedRegistrationBilling($resources['patients'], $resources['cashier'], $billing);
                $this->seedHistoricalConsultations($resources, $billing, $laboratory, $prescriptions, $pharmacy, $medicationAdministration);
                $this->seedCurrentQueues($resources, $billing, $laboratory, $prescriptions);
                $this->seedExternalLaboratoryRequests($resources, $billing);
                $this->seedAdmissions($resources, $billing, $laboratory, $prescriptions, $pharmacy, $medicationAdministration);
                $this->seedUpcomingAppointments($resources);
            }, 3);
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info("Seeded complete outpatient, laboratory, pharmacy, admission, medication, appointment, and billing workflows for hospital ID {$this->hospitalId}.");
    }

    /**
     * @return array{
     *     patients: Collection<int, Patient>, departments: Collection<string, Department>,
     *     doctors: Collection<int, Collection<int, User>>, nurses: Collection<int, Collection<int, User>>,
     *     receptionist: User, cashier: User, pharmacist: User, lab_staff: User,
     *     tests: Collection<int, LabTest>, drugs: Collection<int, Drug>, injection: Drug, oral_drug: Drug
     * }
     */
    private function resources(): array
    {
        $patients = Patient::query()->whereLike('email', 'patient%@%.demo.patient.test')->orderBy('id')->get();
        $departments = Department::query()
            ->whereIn('name', ['General Medicine', 'Paediatrics', 'Obstetrics and Gynaecology'])
            ->get()
            ->keyBy('name');
        $doctors = User::query()->where('role', Role::Doctor->value)->whereNotNull('department_id')->get()->groupBy('department_id');
        $nurses = User::query()->whereIn('role', [Role::Nurse->value, Role::ChiefNurse->value])->whereNotNull('department_id')->get()->groupBy('department_id');
        $receptionist = User::query()->where('role', Role::Receptionist->value)->orderBy('id')->first();
        $cashier = User::query()->whereIn('role', [Role::Accountant->value, Role::ChiefAccountant->value])->orderBy('id')->first();
        $pharmacist = User::query()->whereIn('role', [Role::Pharmacist->value, Role::ChiefPharmacist->value])->orderBy('id')->first();
        $labStaff = User::query()->where('role', Role::Lab->value)->orderBy('id')->first();
        $tests = LabTest::active()->orderBy('id')->get();
        $drugs = Drug::active()->orderBy('id')->get();
        $injection = $drugs->first(fn (Drug $drug): bool => str_contains(strtolower((string) $drug->form), 'inject'));
        $oralDrug = $drugs->first(fn (Drug $drug): bool => ! str_contains(strtolower((string) $drug->form), 'inject') && $drug->available_quantity >= 20);

        if (
            $patients->count() !== 40
            || $departments->count() !== 3
            || ! $receptionist
            || ! $cashier
            || ! $pharmacist
            || ! $labStaff
            || ! $injection
            || ! $oralDrug
            || $tests->count() < 4
        ) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} is missing full-demo foundation data. Run FullHospitalFoundationSeeder first.");
        }

        foreach ($departments as $department) {
            if (($doctors[$department->id] ?? collect())->isEmpty() || ($nurses[$department->id] ?? collect())->isEmpty()) {
                throw new RuntimeException("{$department->name} needs an assigned doctor and nurse before workflow seeding.");
            }
        }

        return [
            'patients' => $patients,
            'departments' => $departments,
            'doctors' => $doctors,
            'nurses' => $nurses,
            'receptionist' => $receptionist,
            'cashier' => $cashier,
            'pharmacist' => $pharmacist,
            'lab_staff' => $labStaff,
            'tests' => $tests,
            'drugs' => $drugs,
            'injection' => $injection,
            'oral_drug' => $oralDrug,
        ];
    }

    private function seedRegistrationBilling(Collection $patients, User $cashier, BillingService $billing): void
    {
        foreach ($patients as $index => $patient) {
            $invoice = $billing->registrationInvoiceFor($patient);
            if ($invoice) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'registration')->get(),
                    $cashier,
                    sprintf('FULL-H%d-REG-%02d', $this->hospitalId, $index + 1),
                    now()->subDays(100 - $index),
                );
            }

            $patient->update(['registration_paid_at' => now()->subDays(100 - $index)]);
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedHistoricalConsultations(
        array $resources,
        BillingService $billing,
        LabWorkflowService $laboratory,
        PrescriptionService $prescriptions,
        PharmacyService $pharmacy,
        MedicationAdministrationService $medicationAdministration,
    ): void {
        $patientIndexes = [0, 0, 1, 1, 2, 2, 3, 3, 4, 5, 6, 7, 8, 9, 10, 11];

        foreach ($patientIndexes as $encounterIndex => $patientIndex) {
            $encounterNumber = $encounterIndex + 1;
            $patient = $resources['patients'][$patientIndex];
            $department = $this->departmentFor($patient, $resources['departments']);
            $doctor = $resources['doctors'][$department->id]->first();
            $nurse = $resources['nurses'][$department->id]->first();
            $consultationType = ConsultationType::active()->where('department_id', $department->id)->oldest('id')->firstOrFail();
            $encounterAt = now()->subDays(12 + ($encounterNumber * 5))->setTime(8 + ($encounterNumber % 7), ($encounterNumber * 7) % 60);
            $appointment = null;

            if ($encounterNumber % 2 === 0) {
                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'type' => 'consultation',
                    'scheduled_at' => $encounterAt,
                    'reason' => 'Booked outpatient review',
                    'status' => AppointmentStatus::Completed,
                    'created_by' => $resources['receptionist']->id,
                ]);
            }

            $visit = Visit::create([
                'patient_id' => $patient->id,
                'appointment_id' => $appointment?->id,
                'doctor_id' => $doctor->id,
                'department_id' => $department->id,
                'consultation_type_id' => $consultationType->id,
                'type' => 'consultation',
                'status' => VisitStatus::InConsultation,
                'complaint' => $this->complaintFor($encounterNumber),
                'checked_in_at' => $encounterAt,
                'triaged_at' => $encounterAt->copy()->addMinutes(15),
                'consulted_at' => $encounterAt->copy()->addMinutes(35),
                'clinical_notes' => self::MARKER.' Historical consultation in a longitudinal patient record.',
            ]);
            $this->recordVitals($visit, $nurse, $encounterNumber, $encounterAt->copy()->addMinutes(15));

            if ($encounterNumber % 4 !== 0) {
                $testCount = $encounterNumber % 5 === 0 ? 2 : 1;
                $laboratory->requestTests(
                    $visit,
                    $resources['tests']->slice($encounterNumber % $resources['tests']->count(), $testCount)->modelKeys(),
                    $doctor->id,
                );
            }

            $prescription = null;
            if ($encounterNumber % 3 !== 1) {
                $drug = $encounterNumber % 7 === 0 ? $resources['injection'] : $resources['oral_drug'];
                $requiresAdministration = $drug->is($resources['injection']);
                $result = $prescriptions->prescribe($visit, [
                    'drug_id' => $drug->id,
                    'dosage' => $requiresAdministration ? '80 mg' : '1 tablet',
                    'frequency' => $requiresAdministration ? 'Once' : 'Twice daily',
                    'duration' => $requiresAdministration ? '1 day' : '5 days',
                    'quantity' => $requiresAdministration ? 1 : 10,
                    'instructions' => $requiresAdministration ? 'Administer in the facility.' : 'Take after food.',
                    'requires_in_facility_administration' => $requiresAdministration,
                    'required_administration_count' => 1,
                ], $doctor->id);
                if ($result['error'] === null) {
                    $prescriptions->placeOrder($visit, $doctor->id);
                    $prescription = $visit->prescriptions()->latest('id')->first();
                }
            }

            $invoice = $billing->syncFor($visit);
            $itemsToSettle = match ($encounterNumber % 5) {
                0 => collect(),
                1 => $invoice->items()->where('category', 'consultation')->get(),
                2 => $invoice->items()->whereIn('category', ['consultation', 'lab'])->get(),
                default => $invoice->items()->get(),
            };
            $this->settleItems($invoice, $itemsToSettle, $resources['cashier'], "FULL-H{$this->hospitalId}-VISIT-{$encounterNumber}", $encounterAt->copy()->addMinutes(50));

            $visit->update([
                'status' => VisitStatus::Completed,
                'diagnosis' => $this->diagnosisFor($encounterNumber),
                'clinical_notes' => self::MARKER.' Consultation completed with assessment, treatment, and follow-up advice.',
                'closed_at' => $encounterAt->copy()->addHours(2),
            ]);

            $this->completePaidLaboratoryRequests($visit, $resources['lab_staff'], $doctor, $encounterAt->copy()->addHour());

            if ($prescription?->fresh()->invoiceItem?->is_paid) {
                $pharmacy->dispense($visit, [$prescription->id], $resources['pharmacist']->id);
                $prescription->refresh();

                if ($prescription->requires_in_facility_administration && $prescription->status === WorkStatus::Completed) {
                    $record = $medicationAdministration->record($visit, $prescription, [
                        'status' => 'administered',
                        'dose' => $prescription->dosage,
                        'route' => 'intramuscular',
                        'site' => 'left deltoid',
                        'notes' => 'Medication rights checked before administration.',
                    ], $nurse);
                    $record->update(['administered_at' => $encounterAt->copy()->addHours(2)]);
                }
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedCurrentQueues(
        array $resources,
        BillingService $billing,
        LabWorkflowService $laboratory,
        PrescriptionService $prescriptions,
    ): void {
        $statuses = [
            VisitStatus::WaitingTriage, VisitStatus::WaitingTriage, VisitStatus::WaitingTriage,
            VisitStatus::WaitingDoctor, VisitStatus::WaitingDoctor, VisitStatus::WaitingDoctor,
            VisitStatus::InConsultation, VisitStatus::InConsultation,
        ];

        foreach ($statuses as $index => $status) {
            $patient = $resources['patients'][12 + $index];
            $department = $this->departmentFor($patient, $resources['departments']);
            $doctor = $resources['doctors'][$department->id]->first();
            $nurse = $resources['nurses'][$department->id]->first();
            $consultationType = ConsultationType::active()->where('department_id', $department->id)->oldest('id')->firstOrFail();
            $checkedInAt = now()->subMinutes(100 - ($index * 11));
            $hasTriage = $status !== VisitStatus::WaitingTriage;

            $visit = Visit::create([
                'patient_id' => $patient->id,
                'doctor_id' => $status === VisitStatus::WaitingTriage ? null : $doctor->id,
                'department_id' => $department->id,
                'consultation_type_id' => $consultationType->id,
                'type' => 'consultation',
                'status' => $status,
                'complaint' => $this->complaintFor(30 + $index),
                'checked_in_at' => $checkedInAt,
                'triaged_at' => $hasTriage ? $checkedInAt->copy()->addMinutes(15) : null,
                'consulted_at' => $status === VisitStatus::InConsultation ? $checkedInAt->copy()->addMinutes(30) : null,
                'clinical_notes' => self::MARKER.' Active outpatient queue case.',
            ]);

            if ($hasTriage) {
                $this->recordVitals($visit, $nurse, 30 + $index, $checkedInAt->copy()->addMinutes(15));
            }

            if ($status === VisitStatus::InConsultation && $index === 6) {
                $laboratory->requestTests($visit, [$resources['tests']->first()->id], $doctor->id);
            }

            if ($status === VisitStatus::InConsultation && $index === 7) {
                $prescriptions->prescribe($visit, [
                    'drug_id' => $resources['oral_drug']->id,
                    'dosage' => '1 tablet',
                    'frequency' => 'Twice daily',
                    'duration' => '5 days',
                    'quantity' => 10,
                    'instructions' => 'Take after food.',
                    'requires_in_facility_administration' => false,
                ], $doctor->id);
            }

            $invoice = $billing->syncFor($visit);
            if ($index !== 0 && $index !== 7) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'consultation')->get(),
                    $resources['cashier'],
                    "FULL-H{$this->hospitalId}-CURRENT-{$index}",
                    $checkedInAt->copy()->addMinutes(10),
                );
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedExternalLaboratoryRequests(array $resources, BillingService $billing): void
    {
        foreach ([20, 21, 22] as $caseIndex => $patientIndex) {
            $patient = $resources['patients'][$patientIndex];
            $checkedInAt = now()->subDays(3 - $caseIndex)->setTime(10 + $caseIndex, 0);
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
                'complaint' => 'External laboratory investigations requested.',
                'referred_from' => ['St. Anne Clinic', 'New Haven Medical Centre', 'Community Health Centre'][$caseIndex],
                'referred_by' => ['Dr. Chidi Eze', 'Dr. Nneka Obi', 'Dr. Ifeanyi Okoro'][$caseIndex],
                'checked_in_at' => $checkedInAt,
                'clinical_notes' => self::MARKER.' External laboratory referral.',
            ]);

            foreach ($resources['tests']->slice($caseIndex * 2, 2) as $test) {
                $visit->labRequests()->create([
                    'lab_test_id' => $test->id,
                    'requested_by' => $resources['receptionist']->id,
                    'status' => WorkStatus::Pending,
                ]);
            }

            $invoice = $billing->syncFor($visit);
            if ($caseIndex !== 2) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'lab')->get(),
                    $resources['cashier'],
                    "FULL-H{$this->hospitalId}-EXTLAB-{$caseIndex}",
                    $checkedInAt->copy()->addMinutes(20),
                );
            }

            if ($caseIndex === 0) {
                $this->completePaidLaboratoryRequests($visit, $resources['lab_staff'], null, $checkedInAt->copy()->addHours(3));
                $visit->update(['status' => VisitStatus::Completed, 'closed_at' => $checkedInAt->copy()->addHours(3)]);
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedAdmissions(
        array $resources,
        BillingService $billing,
        LabWorkflowService $laboratory,
        PrescriptionService $prescriptions,
        PharmacyService $pharmacy,
        MedicationAdministrationService $medicationAdministration,
    ): void {
        $department = $resources['departments']['General Medicine'];
        $doctor = $resources['doctors'][$department->id]->first();
        $nurse = $resources['nurses'][$department->id]->first();
        $consultationType = ConsultationType::active()->where('department_id', $department->id)->oldest('id')->firstOrFail();

        foreach (range(0, 4) as $caseIndex) {
            $patient = $resources['patients'][23 + $caseIndex];
            $admittedAt = now()->subDays(9 - $caseIndex)->setTime(11, 0);
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'department_id' => $department->id,
                'consultation_type_id' => $consultationType->id,
                'type' => 'consultation',
                'status' => VisitStatus::InConsultation,
                'complaint' => ['Persistent fever and weakness', 'Severe gastroenteritis', 'Community-acquired pneumonia', 'Poorly controlled hypertension', 'Acute pyelonephritis'][$caseIndex],
                'checked_in_at' => $admittedAt->copy()->subHour(),
                'triaged_at' => $admittedAt->copy()->subMinutes(40),
                'consulted_at' => $admittedAt->copy()->subMinutes(20),
                'clinical_notes' => self::MARKER.' Consultation escalated to inpatient admission.',
            ]);
            $this->recordVitals($visit, $nurse, 50 + $caseIndex, $admittedAt->copy()->subMinutes(40));
            $laboratory->requestTests($visit, $resources['tests']->slice($caseIndex, 2)->modelKeys(), $doctor->id);

            $result = $prescriptions->prescribe($visit, [
                'drug_id' => $resources['injection']->id,
                'dosage' => '80 mg',
                'frequency' => 'Once',
                'duration' => '1 day',
                'quantity' => 1,
                'instructions' => 'Administer in the ward after pharmacy dispensing.',
                'requires_in_facility_administration' => true,
                'required_administration_count' => 1,
            ], $doctor->id);
            if ($result['error'] !== null) {
                throw new RuntimeException($result['error']);
            }

            $prescriptions->placeOrder($visit, $doctor->id);
            $prescription = $visit->prescriptions()->latest('id')->firstOrFail();
            $invoice = $billing->syncFor($visit);
            $this->settleItems(
                $invoice,
                $caseIndex === 4 ? $invoice->items()->whereIn('category', ['consultation', 'drug'])->get() : $invoice->items()->get(),
                $resources['cashier'],
                "FULL-H{$this->hospitalId}-ADM-{$caseIndex}",
                $admittedAt->copy()->addMinutes(30),
            );

            $isActive = $caseIndex < 3;
            $visit->update([
                'status' => VisitStatus::Inpatient,
                'diagnosis' => ['Severe malaria', 'Acute gastroenteritis with dehydration', 'Community-acquired pneumonia', 'Hypertensive urgency', 'Acute pyelonephritis'][$caseIndex],
                'closed_at' => $admittedAt,
            ]);

            $admission = Admission::create([
                'admission_number' => sprintf('FULL-H%d-ADM-%02d', $this->hospitalId, $caseIndex + 1),
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'department_id' => $department->id,
                'attending_doctor_id' => $doctor->id,
                'admitted_by' => $doctor->id,
                'status' => $isActive ? AdmissionStatus::Active : AdmissionStatus::Discharged,
                'admitted_at' => $admittedAt,
                'admission_reason' => 'Required inpatient monitoring, investigations, and treatment.',
                'admission_diagnosis' => $visit->diagnosis,
                'initial_treatment_plan' => 'Monitor observations, complete investigations, administer prescribed treatment, and review daily.',
                'expected_discharge_date' => $admittedAt->copy()->addDays(4)->toDateString(),
                'discharged_by' => $isActive ? null : $doctor->id,
                'discharged_at' => $isActive ? null : $admittedAt->copy()->addDays(3),
                'discharge_diagnosis' => $isActive ? null : $visit->diagnosis,
                'discharge_summary' => $isActive ? null : 'Patient improved after inpatient treatment and was stable for discharge.',
                'discharge_medications' => $isActive ? null : 'Continue prescribed oral medicines as directed.',
                'follow_up_instructions' => $isActive ? null : 'Medical outpatient review in one week.',
                'patient_discharge_instructions' => $isActive ? null : 'Return immediately if symptoms recur or worsen.',
                'pending_results_plan' => $isActive ? null : 'Attending doctor will review any outstanding results.',
            ]);

            $admission->vitals()->create([
                'recorded_by' => $nurse->id,
                'temperature' => 37.4 + ($caseIndex / 10),
                'pulse' => 82 + $caseIndex,
                'respiratory_rate' => 18 + $caseIndex,
                'bp_systolic' => 118 + ($caseIndex * 4),
                'bp_diastolic' => 76 + ($caseIndex * 2),
                'spo2' => 97,
                'weight' => 62 + $caseIndex,
                'height' => 168,
                'notes' => 'Ward admission observations.',
                'recorded_at' => $admittedAt->copy()->addMinutes(10),
            ]);
            $admission->notes()->create([
                'author_id' => $doctor->id,
                'note_type' => 'medical',
                'note' => 'Reviewed on the ward. Continue treatment plan and monitor response.',
            ]);
            $admission->notes()->create([
                'author_id' => $nurse->id,
                'note_type' => 'nursing',
                'note' => 'Patient settled in the ward; prescribed care and observations commenced.',
            ]);

            $completeLabs = $caseIndex !== 2;
            if ($completeLabs) {
                $this->completePaidLaboratoryRequests($visit, $resources['lab_staff'], $caseIndex === 0 ? null : $doctor, $admittedAt->copy()->addHours(4));
            }

            if ($prescription->fresh()->invoiceItem?->is_paid) {
                $pharmacy->dispense($visit, [$prescription->id], $resources['pharmacist']->id);
                $prescription->refresh();

                if ($prescription->status === WorkStatus::Completed) {
                    $record = $medicationAdministration->record($visit, $prescription, [
                        'status' => 'administered',
                        'dose' => '80 mg',
                        'route' => 'intramuscular',
                        'site' => 'right gluteal',
                        'notes' => 'Dose administered and response monitored.',
                    ], $nurse);
                    $record->update(['administered_at' => $admittedAt->copy()->addHour()]);
                }
            }

            if (! $isActive) {
                $visit->update([
                    'status' => VisitStatus::Completed,
                    'discharged_at' => $admittedAt->copy()->addDays(3),
                    'discharged_by' => $doctor->id,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedUpcomingAppointments(array $resources): void
    {
        foreach (range(0, 5) as $index) {
            $patient = $resources['patients'][28 + $index];
            $department = $this->departmentFor($patient, $resources['departments']);
            Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $resources['doctors'][$department->id]->first()->id,
                'type' => 'consultation',
                'scheduled_at' => now()->addDays($index + 1)->setTime(9 + $index, 0),
                'reason' => 'Booked outpatient consultation',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $resources['receptionist']->id,
            ]);
        }

        foreach (range(0, 1) as $index) {
            $appointment = Appointment::create([
                'patient_id' => $resources['patients'][34 + $index]->id,
                'doctor_id' => null,
                'type' => 'lab',
                'scheduled_at' => now()->addDays($index + 2)->setTime(10 + $index, 30),
                'reason' => 'Booked laboratory investigations',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $resources['receptionist']->id,
            ]);
            $appointment->labTests()->sync($resources['tests']->slice($index * 2, 2)->modelKeys());
        }
    }

    private function departmentFor(Patient $patient, Collection $departments): Department
    {
        if (($patient->age ?? 30) < 17) {
            return $departments['Paediatrics'];
        }

        if ($patient->gender === 'female' && ($patient->age ?? 30) <= 45 && $patient->id % 4 === 0) {
            return $departments['Obstetrics and Gynaecology'];
        }

        return $departments['General Medicine'];
    }

    private function recordVitals(Visit $visit, User $nurse, int $number, Carbon $recordedAt): void
    {
        $visit->vitals()->create([
            'recorded_by' => $nurse->id,
            'temperature' => [36.7, 37.1, 37.6, 38.1][$number % 4],
            'pulse' => 68 + ($number % 34),
            'respiratory_rate' => 15 + ($number % 6),
            'bp_systolic' => 108 + ($number % 31),
            'bp_diastolic' => 68 + ($number % 17),
            'spo2' => 96 + ($number % 4),
            'weight' => 48 + ($number % 38),
            'height' => 150 + ($number % 30),
            'notes' => 'Triage observations recorded by nursing staff.',
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ]);
    }

    private function completePaidLaboratoryRequests(Visit $visit, User $labStaff, ?User $reviewer, Carbon $completedAt): void
    {
        foreach ($visit->labRequests()->with('invoiceItem')->get() as $request) {
            if (! $request->invoiceItem?->is_paid) {
                continue;
            }

            $request->update([
                'result' => 'Investigation completed. Findings documented and released by the laboratory scientist.',
                'status' => WorkStatus::Completed,
                'processed_by' => $labStaff->id,
                'completed_at' => $completedAt,
                'result_reviewed_by' => $reviewer?->id,
                'result_reviewed_at' => $reviewer ? $completedAt->copy()->addMinutes(20) : null,
                'result_review_note' => $reviewer ? 'Result reviewed and incorporated into the care plan.' : null,
            ]);
        }
    }

    private function settleItems(Invoice $invoice, Collection $items, User $cashier, string $reference, Carbon $paidAt): ?Payment
    {
        $items = $items->whereNull('payment_id');
        $amount = (float) $items->sum('total');

        if ($items->isEmpty() || $amount <= 0) {
            return null;
        }

        $payment = $invoice->payments()->create([
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'pos'][$invoice->id % 3],
            'reference' => $reference,
            'received_by' => $cashier->id,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        $invoice->items()->whereIn('id', $items->modelKeys())->update(['payment_id' => $payment->id]);
        $invoice->recalculate();

        return $payment;
    }

    private function complaintFor(int $number): string
    {
        return [
            'Fever, body pains, and weakness',
            'Persistent headache and dizziness',
            'Abdominal discomfort and poor appetite',
            'Cough, nasal congestion, and malaise',
            'Routine follow-up review',
            'Back pain following physical activity',
        ][$number % 6];
    }

    private function diagnosisFor(int $number): string
    {
        return [
            'Acute febrile illness; stable for outpatient care.',
            'Tension-type headache; supportive management advised.',
            'Dyspepsia; lifestyle and treatment advice provided.',
            'Upper respiratory tract infection.',
            'Clinical condition improving at follow-up.',
            'Mechanical lower back pain.',
        ][$number % 6];
    }
}
