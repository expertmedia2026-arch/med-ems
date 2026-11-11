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

class UrologyCentreWorkflowSeeder extends Seeder
{
    public int $hospitalId = 1;

    private const MARKER = '[UROLOGY_CENTRE_DEMO]';

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
                $this->command?->info("Urology workflow demo already exists for hospital ID {$this->hospitalId}; no changes made.");

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

                $this->seedRegistrationBilling($resources, $billing);
                $this->seedHistoricalConsultations($resources, $billing, $laboratory, $prescriptions, $pharmacy, $medicationAdministration);
                $this->seedCurrentQueues($resources, $billing, $laboratory, $prescriptions);
                $this->seedExternalLaboratoryRequests($resources, $billing);
                $this->seedAdmissions($resources, $billing, $laboratory, $prescriptions, $pharmacy, $medicationAdministration);
                $this->seedUpcomingAppointments($resources);
            }, 3);
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info("Seeded specialist urology consultations, laboratory, pharmacy, admissions, appointments, and billing for hospital ID {$this->hospitalId}.");
    }

    /** @return array<string, mixed> */
    private function resources(): array
    {
        $patients = Patient::query()->whereLike('email', 'patient%@%.urology.demo.patient.test')->orderBy('id')->get();
        $clinic = Department::query()->where('name', 'Urology Outpatient Clinic')->first();
        $ward = Department::query()->where('name', 'Urology Ward')->first();
        $doctor = $clinic ? User::query()->where('role', Role::Doctor->value)->where('department_id', $clinic->id)->oldest('id')->first() : null;
        $clinicNurse = $clinic ? User::query()->whereIn('role', [Role::Nurse->value, Role::ChiefNurse->value])->where('department_id', $clinic->id)->oldest('id')->first() : null;
        $wardNurse = $ward ? User::query()->whereIn('role', [Role::Nurse->value, Role::ChiefNurse->value])->where('department_id', $ward->id)->oldest('id')->first() : null;
        $receptionist = User::query()->where('role', Role::Receptionist->value)->oldest('id')->first();
        $cashier = User::query()->whereIn('role', [Role::Accountant->value, Role::ChiefAccountant->value])->oldest('id')->first();
        $pharmacist = User::query()->whereIn('role', [Role::Pharmacist->value, Role::ChiefPharmacist->value])->oldest('id')->first();
        $labStaff = User::query()->where('role', Role::Lab->value)->oldest('id')->first();
        $consultationTypes = $clinic ? ConsultationType::active()->where('department_id', $clinic->id)->oldest('id')->get() : collect();
        $tests = LabTest::active()->oldest('id')->get();
        $drugs = Drug::active()->oldest('id')->get();
        $injection = $drugs->first(fn (Drug $drug): bool => $drug->name === 'Ceftriaxone' && $drug->available_quantity > 0)
            ?? $drugs->first(fn (Drug $drug): bool => str_contains(strtolower((string) $drug->form), 'inject') && $drug->available_quantity > 0);
        $oralDrug = $drugs->first(fn (Drug $drug): bool => $drug->name === 'Tamsulosin')
            ?? $drugs->first(fn (Drug $drug): bool => ! str_contains(strtolower((string) $drug->form), 'inject'));

        if (
            $patients->count() !== 30
            || ! $clinic
            || ! $ward
            || ! $doctor
            || ! $clinicNurse
            || ! $wardNurse
            || ! $receptionist
            || ! $cashier
            || ! $pharmacist
            || ! $labStaff
            || $consultationTypes->count() !== 5
            || $tests->count() < 12
            || ! $injection
            || ! $oralDrug
        ) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} is missing the urology foundation. Run UrologyCentreFoundationSeeder first.");
        }

        return compact(
            'patients', 'clinic', 'ward', 'doctor', 'clinicNurse', 'wardNurse',
            'receptionist', 'cashier', 'pharmacist', 'labStaff', 'consultationTypes',
            'tests', 'drugs', 'injection', 'oralDrug',
        );
    }

    /** @param array<string, mixed> $resources */
    private function seedRegistrationBilling(array $resources, BillingService $billing): void
    {
        foreach ($resources['patients'] as $index => $patient) {
            $invoice = $billing->registrationInvoiceFor($patient);
            if ($invoice) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'registration')->get(),
                    $resources['cashier'],
                    sprintf('URO-H%d-REG-%02d', $this->hospitalId, $index + 1),
                    now()->subDays(80 - $index),
                );
            }

            $patient->update(['registration_paid_at' => now()->subDays(80 - $index)]);
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
        $patientIndexes = [0, 0, 1, 1, 2, 3, 4, 5, 6, 7, 8, 9];

        foreach ($patientIndexes as $encounterIndex => $patientIndex) {
            $number = $encounterIndex + 1;
            $patient = $resources['patients'][$patientIndex];
            $encounterAt = now()->subDays(10 + ($number * 6))->setTime(8 + ($number % 7), ($number * 9) % 60);
            $consultationType = $resources['consultationTypes'][($number - 1) % $resources['consultationTypes']->count()];
            $appointment = null;

            if ($number % 2 === 0) {
                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $resources['doctor']->id,
                    'type' => 'consultation',
                    'scheduled_at' => $encounterAt,
                    'reason' => 'Booked specialist urology review',
                    'status' => AppointmentStatus::Completed,
                    'created_by' => $resources['receptionist']->id,
                ]);
            }

            $visit = Visit::create([
                'patient_id' => $patient->id,
                'appointment_id' => $appointment?->id,
                'doctor_id' => $resources['doctor']->id,
                'department_id' => $resources['clinic']->id,
                'consultation_type_id' => $consultationType->id,
                'type' => 'consultation',
                'status' => VisitStatus::InConsultation,
                'complaint' => $this->complaintFor($number),
                'checked_in_at' => $encounterAt,
                'triaged_at' => $encounterAt->copy()->addMinutes(15),
                'consulted_at' => $encounterAt->copy()->addMinutes(35),
                'clinical_notes' => self::MARKER.' Historical specialist urology consultation.',
            ]);
            $this->recordVitals($visit, $resources['clinicNurse'], $number, $encounterAt->copy()->addMinutes(15));

            if ($number % 3 !== 0) {
                $laboratory->requestTests(
                    $visit,
                    $resources['tests']->slice($number % $resources['tests']->count(), $number % 4 === 0 ? 2 : 1)->modelKeys(),
                    $resources['doctor']->id,
                );
            }

            $prescription = null;
            if ($number % 2 === 0) {
                $drug = $number % 6 === 0 ? $resources['injection'] : $resources['oralDrug'];
                $requiresAdministration = $drug->is($resources['injection']);
                $result = $prescriptions->prescribe($visit, [
                    'drug_id' => $drug->id,
                    'dosage' => $requiresAdministration ? '1 g' : '1 capsule',
                    'frequency' => $requiresAdministration ? 'Once' : 'Once daily',
                    'duration' => $requiresAdministration ? '1 day' : '14 days',
                    'quantity' => $requiresAdministration ? 1 : 14,
                    'instructions' => $requiresAdministration ? 'Administer in the facility.' : 'Take as directed by the urologist.',
                    'requires_in_facility_administration' => $requiresAdministration,
                    'required_administration_count' => 1,
                ], $resources['doctor']->id);
                if ($result['error'] === null) {
                    $prescriptions->placeOrder($visit, $resources['doctor']->id);
                    $prescription = $visit->prescriptions()->latest('id')->first();
                }
            }

            $invoice = $billing->syncFor($visit);
            $settledItems = match ($number % 4) {
                0 => collect(),
                1 => $invoice->items()->where('category', 'consultation')->get(),
                2 => $invoice->items()->get(),
                default => $invoice->items()->whereIn('category', ['consultation', 'lab'])->get(),
            };
            $this->settleItems(
                $invoice,
                $settledItems,
                $resources['cashier'],
                "URO-H{$this->hospitalId}-VISIT-{$number}",
                $encounterAt->copy()->addMinutes(50),
            );

            $visit->update([
                'status' => VisitStatus::Completed,
                'diagnosis' => $this->diagnosisFor($number),
                'clinical_notes' => self::MARKER.' Specialist assessment completed with investigations and treatment plan.',
                'closed_at' => $encounterAt->copy()->addHours(2),
            ]);
            $this->completePaidLaboratoryRequests($visit, $resources['labStaff'], $resources['doctor'], $encounterAt->copy()->addHour());

            if ($prescription?->fresh()->invoiceItem?->is_paid) {
                $pharmacy->dispense($visit, [$prescription->id], $resources['pharmacist']->id);
                $prescription->refresh();

                if ($prescription->requires_in_facility_administration && $prescription->status === WorkStatus::Completed) {
                    $record = $medicationAdministration->record($visit, $prescription, [
                        'status' => 'administered',
                        'dose' => $prescription->dosage,
                        'route' => 'intravenous',
                        'site' => 'left forearm',
                        'notes' => 'Medicine administered after identity and allergy checks.',
                    ], $resources['clinicNurse']);
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
            VisitStatus::WaitingTriage,
            VisitStatus::WaitingDoctor,
            VisitStatus::WaitingDoctor,
            VisitStatus::InConsultation,
            VisitStatus::InConsultation,
        ];

        foreach ($statuses as $index => $status) {
            $patient = $resources['patients'][10 + $index];
            $checkedInAt = now()->subMinutes(75 - ($index * 12));
            $hasTriage = $status !== VisitStatus::WaitingTriage;
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'doctor_id' => $status === VisitStatus::WaitingTriage ? null : $resources['doctor']->id,
                'department_id' => $resources['clinic']->id,
                'consultation_type_id' => $resources['consultationTypes'][$index % $resources['consultationTypes']->count()]->id,
                'type' => 'consultation',
                'status' => $status,
                'complaint' => $this->complaintFor(20 + $index),
                'checked_in_at' => $checkedInAt,
                'triaged_at' => $hasTriage ? $checkedInAt->copy()->addMinutes(12) : null,
                'consulted_at' => $status === VisitStatus::InConsultation ? $checkedInAt->copy()->addMinutes(30) : null,
                'clinical_notes' => self::MARKER.' Active specialist clinic queue case.',
            ]);

            if ($hasTriage) {
                $this->recordVitals($visit, $resources['clinicNurse'], 20 + $index, $checkedInAt->copy()->addMinutes(12));
            }

            if ($status === VisitStatus::InConsultation && $index === 3) {
                $laboratory->requestTests($visit, [$resources['tests']->first()->id], $resources['doctor']->id);
            }

            if ($status === VisitStatus::InConsultation && $index === 4) {
                $prescriptions->prescribe($visit, [
                    'drug_id' => $resources['oralDrug']->id,
                    'dosage' => '1 capsule',
                    'frequency' => 'Once daily',
                    'duration' => '14 days',
                    'quantity' => 14,
                    'instructions' => 'Take as directed by the urologist.',
                    'requires_in_facility_administration' => false,
                ], $resources['doctor']->id);
            }

            $invoice = $billing->syncFor($visit);
            if ($index !== 0 && $index !== 4) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'consultation')->get(),
                    $resources['cashier'],
                    "URO-H{$this->hospitalId}-CURRENT-{$index}",
                    $checkedInAt->copy()->addMinutes(8),
                );
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedExternalLaboratoryRequests(array $resources, BillingService $billing): void
    {
        foreach ([15, 16] as $caseIndex => $patientIndex) {
            $checkedInAt = now()->subDays(2 - $caseIndex)->setTime(10 + $caseIndex, 0);
            $visit = Visit::create([
                'patient_id' => $resources['patients'][$patientIndex]->id,
                'type' => 'lab_only',
                'status' => VisitStatus::LabOnly,
                'complaint' => 'External urology laboratory investigations requested.',
                'referred_from' => ['St. Michael Specialist Clinic', 'Enugu Family Medical Centre'][$caseIndex],
                'referred_by' => ['Dr. Chidi Eze', 'Dr. Nneka Obi'][$caseIndex],
                'checked_in_at' => $checkedInAt,
                'clinical_notes' => self::MARKER.' External urology laboratory referral.',
            ]);

            foreach ($resources['tests']->slice($caseIndex * 2, 2) as $test) {
                $visit->labRequests()->create([
                    'lab_test_id' => $test->id,
                    'requested_by' => $resources['receptionist']->id,
                    'status' => WorkStatus::Pending,
                ]);
            }

            $invoice = $billing->syncFor($visit);
            if ($caseIndex === 0) {
                $this->settleItems(
                    $invoice,
                    $invoice->items()->where('category', 'lab')->get(),
                    $resources['cashier'],
                    "URO-H{$this->hospitalId}-EXTLAB-{$caseIndex}",
                    $checkedInAt->copy()->addMinutes(20),
                );
                $this->completePaidLaboratoryRequests($visit, $resources['labStaff'], null, $checkedInAt->copy()->addHours(3));
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
        $reasons = [
            ['Acute urinary retention', 'Benign prostatic enlargement with acute urinary retention'],
            ['Fever, flank pain, and vomiting', 'Complicated urinary tract infection with pyelonephritis'],
            ['Severe ureteric colic with poor oral intake', 'Obstructing ureteric calculus'],
            ['Gross haematuria with clot retention', 'Macroscopic haematuria requiring inpatient evaluation'],
        ];

        foreach ($reasons as $caseIndex => [$complaint, $diagnosis]) {
            $patient = $resources['patients'][17 + $caseIndex];
            $admittedAt = now()->subDays(7 - $caseIndex)->setTime(11, 0);
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'doctor_id' => $resources['doctor']->id,
                'department_id' => $resources['clinic']->id,
                'consultation_type_id' => $resources['consultationTypes']->first()->id,
                'type' => 'consultation',
                'status' => VisitStatus::InConsultation,
                'complaint' => $complaint,
                'checked_in_at' => $admittedAt->copy()->subHour(),
                'triaged_at' => $admittedAt->copy()->subMinutes(40),
                'consulted_at' => $admittedAt->copy()->subMinutes(20),
                'clinical_notes' => self::MARKER.' Urology consultation escalated to inpatient care.',
            ]);
            $this->recordVitals($visit, $resources['clinicNurse'], 40 + $caseIndex, $admittedAt->copy()->subMinutes(40));
            $laboratory->requestTests($visit, $resources['tests']->slice($caseIndex, 2)->modelKeys(), $resources['doctor']->id);

            $result = $prescriptions->prescribe($visit, [
                'drug_id' => $resources['injection']->id,
                'dosage' => '1 g',
                'frequency' => 'Once',
                'duration' => '1 day',
                'quantity' => 1,
                'instructions' => 'Administer in the urology ward after pharmacy dispensing.',
                'requires_in_facility_administration' => true,
                'required_administration_count' => 1,
            ], $resources['doctor']->id);
            if ($result['error'] !== null) {
                throw new RuntimeException($result['error']);
            }

            $prescriptions->placeOrder($visit, $resources['doctor']->id);
            $prescription = $visit->prescriptions()->latest('id')->firstOrFail();
            $invoice = $billing->syncFor($visit);
            $this->settleItems(
                $invoice,
                $caseIndex === 3 ? $invoice->items()->whereIn('category', ['consultation', 'drug'])->get() : $invoice->items()->get(),
                $resources['cashier'],
                "URO-H{$this->hospitalId}-ADM-{$caseIndex}",
                $admittedAt->copy()->addMinutes(30),
            );

            $isActive = $caseIndex < 3;
            $visit->update([
                'status' => VisitStatus::Inpatient,
                'diagnosis' => $diagnosis,
                'closed_at' => $admittedAt,
            ]);
            $admission = Admission::create([
                'admission_number' => sprintf('URO-H%d-ADM-%02d', $this->hospitalId, $caseIndex + 1),
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'department_id' => $resources['ward']->id,
                'attending_doctor_id' => $resources['doctor']->id,
                'admitted_by' => $resources['doctor']->id,
                'status' => AdmissionStatus::Active,
                'admitted_at' => $admittedAt,
                'admission_reason' => $complaint,
                'admission_diagnosis' => $diagnosis,
                'initial_treatment_plan' => 'Ward monitoring, laboratory evaluation, prescribed treatment, and daily urology review.',
                'expected_discharge_date' => $admittedAt->copy()->addDays(4)->toDateString(),
                'discharged_by' => null,
                'discharged_at' => null,
                'discharge_diagnosis' => null,
                'discharge_summary' => null,
                'discharge_medications' => null,
                'follow_up_instructions' => null,
                'patient_discharge_instructions' => null,
                'pending_results_plan' => null,
            ]);
            $admission->vitals()->create([
                'recorded_by' => $resources['wardNurse']->id,
                'temperature' => 37.2 + ($caseIndex / 10),
                'pulse' => 80 + ($caseIndex * 3),
                'respiratory_rate' => 18,
                'bp_systolic' => 118 + ($caseIndex * 5),
                'bp_diastolic' => 76 + ($caseIndex * 2),
                'spo2' => 97,
                'weight' => 65 + $caseIndex,
                'height' => 170,
                'notes' => 'Initial urology ward observations.',
                'recorded_at' => $admittedAt->copy()->addMinutes(10),
            ]);
            $admission->notes()->create([
                'author_id' => $resources['doctor']->id,
                'note_type' => 'medical',
                'note' => 'Reviewed after admission. Continue the documented urology treatment plan.',
            ]);
            $admission->notes()->create([
                'author_id' => $resources['wardNurse']->id,
                'note_type' => 'nursing',
                'note' => 'Patient received in the ward; observations and prescribed care commenced.',
            ]);

            if ($caseIndex !== 2) {
                $this->completePaidLaboratoryRequests(
                    $visit,
                    $resources['labStaff'],
                    $caseIndex === 0 ? null : $resources['doctor'],
                    $admittedAt->copy()->addHours(4),
                );
            }

            $pharmacy->dispense($visit, [$prescription->id], $resources['pharmacist']->id);
            $prescription->refresh();
            if ($prescription->status === WorkStatus::Completed) {
                $record = $medicationAdministration->record($visit, $prescription, [
                    'status' => 'administered',
                    'dose' => '1 g',
                    'route' => 'intravenous',
                    'site' => 'right forearm',
                    'notes' => 'Dose administered and patient monitored.',
                ], $resources['wardNurse']);
                $record->update(['administered_at' => $admittedAt->copy()->addHour()]);
            }

            if (! $isActive) {
                $admission->update([
                    'status' => AdmissionStatus::Discharged,
                    'discharged_by' => $resources['doctor']->id,
                    'discharged_at' => $admittedAt->copy()->addDays(3),
                    'discharge_diagnosis' => $diagnosis,
                    'discharge_summary' => 'Symptoms improved and the patient was stable for outpatient urology follow-up.',
                    'discharge_medications' => 'Continue prescribed medicines as documented.',
                    'follow_up_instructions' => 'Return to the urology clinic within one week.',
                    'patient_discharge_instructions' => 'Return urgently for inability to pass urine, fever, worsening pain, or bleeding.',
                    'pending_results_plan' => 'The attending urologist will review outstanding results.',
                ]);
                $visit->update([
                    'status' => VisitStatus::Completed,
                    'discharged_at' => $admittedAt->copy()->addDays(3),
                    'discharged_by' => $resources['doctor']->id,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $resources */
    private function seedUpcomingAppointments(array $resources): void
    {
        foreach (range(0, 5) as $index) {
            Appointment::create([
                'patient_id' => $resources['patients'][21 + $index]->id,
                'doctor_id' => $resources['doctor']->id,
                'type' => 'consultation',
                'scheduled_at' => now()->addDays($index + 1)->setTime(9 + $index, 0),
                'reason' => ['Initial urology assessment', 'PSA review', 'Stone clinic follow-up', 'Catheter review', 'Andrology consultation', 'Post-admission review'][$index],
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $resources['receptionist']->id,
            ]);
        }

        foreach (range(0, 1) as $index) {
            $appointment = Appointment::create([
                'patient_id' => $resources['patients'][27 + $index]->id,
                'doctor_id' => null,
                'type' => 'lab',
                'scheduled_at' => now()->addDays($index + 2)->setTime(10 + $index, 30),
                'reason' => 'Booked specialist laboratory investigations',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $resources['receptionist']->id,
            ]);
            $appointment->labTests()->sync($resources['tests']->slice($index * 2, 2)->modelKeys());
        }
    }

    private function recordVitals(Visit $visit, User $nurse, int $number, Carbon $recordedAt): void
    {
        $visit->vitals()->create([
            'recorded_by' => $nurse->id,
            'temperature' => [36.7, 37.0, 37.5, 38.1][$number % 4],
            'pulse' => 68 + ($number % 32),
            'respiratory_rate' => 15 + ($number % 5),
            'bp_systolic' => 110 + ($number % 35),
            'bp_diastolic' => 68 + ($number % 18),
            'spo2' => 96 + ($number % 4),
            'weight' => 55 + ($number % 35),
            'height' => 158 + ($number % 22),
            'notes' => 'Specialist clinic triage observations.',
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
                'result' => 'Specialist investigation completed; findings documented in the laboratory record.',
                'status' => WorkStatus::Completed,
                'processed_by' => $labStaff->id,
                'completed_at' => $completedAt,
                'result_reviewed_by' => $reviewer?->id,
                'result_reviewed_at' => $reviewer ? $completedAt->copy()->addMinutes(20) : null,
                'result_review_note' => $reviewer ? 'Result reviewed by the attending urologist.' : null,
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
            'Poor urinary stream, hesitancy, and nocturia',
            'Burning urination with urinary frequency',
            'Intermittent flank pain radiating to the groin',
            'Visible blood in urine',
            'Elevated PSA requiring specialist review',
            'Male fertility assessment',
            'Catheter discomfort and review request',
            'Erectile dysfunction requiring specialist assessment',
        ][$number % 8];
    }

    private function diagnosisFor(int $number): string
    {
        return [
            'Benign prostatic enlargement with lower urinary tract symptoms.',
            'Urinary tract infection; outpatient treatment and review planned.',
            'Suspected ureteric calculus; investigations requested.',
            'Haematuria requiring structured urological evaluation.',
            'Raised PSA under specialist evaluation.',
            'Male factor infertility under investigation.',
            'Catheter-related symptoms; care plan documented.',
            'Erectile dysfunction; risk assessment and follow-up arranged.',
        ][$number % 8];
    }
}
