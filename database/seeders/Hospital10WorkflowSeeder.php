<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\DrugInventoryService;
use App\Services\LabWorkflowService;
use App\Services\MedicationAdministrationService;
use App\Services\PharmacyService;
use App\Services\PrescriptionService;
use App\Services\WorkflowNotificationService;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class Hospital10WorkflowSeeder extends Seeder
{
    private const HOSPITAL_ID = 10;

    private const MARKER = '[HOSPITAL10_DEMO_WORKFLOW]';

    public function run(): void
    {
        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set(self::HOSPITAL_ID);

        try {
            // Workflow fixtures should not flood real staff inboxes with hundreds
            // of synthetic alerts while model hooks exercise the normal pathways.
            Notification::fake();

            DB::transaction(function (): void {
                if (Visit::query()->where('clinical_notes', 'like', '%'.self::MARKER.'%')->exists()) {
                    $this->command?->info('Hospital 10 workflow data is already seeded; no changes made.');

                    return;
                }

                if (Visit::query()->exists()) {
                    throw new RuntimeException('Hospital 10 already has visits. This demo seeder only runs on a fresh visit history.');
                }

                $patients = Patient::query()->orderBy('id')->get();
                if ($patients->count() !== 40) {
                    throw new RuntimeException('Expected exactly 40 existing patients for hospital 10; found '.$patients->count().'.');
                }

                // The tenant currently has the laboratory and pharmacy modules enabled,
                // but no local catalogs. Seed their standard catalog only in that fresh state.
                if (LabTest::query()->count() === 0 && Drug::query()->count() === 0) {
                    $this->call(ClinicalSeeder::class);
                }

                $this->seedWorkflow($patients);
            }, 3);
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info('Hospital 10 consultation, lab, pharmacy, administration, billing, and appointment workflows are ready.');
    }

    private function seedWorkflow(EloquentCollection $patients): void
    {
        $departments = Department::query()->whereIn('name', ['General Medicine', 'Paediatrics', 'Maternity'])->get()->keyBy('name');
        $doctors = User::query()->where('role', Role::Doctor->value)->whereNotNull('department_id')->get()->groupBy('department_id');
        $nurses = User::query()->where('role', Role::Nurse->value)->whereNotNull('department_id')->get()->groupBy('department_id');
        $receptionist = User::query()->where('role', Role::Receptionist->value)->orderBy('id')->first();
        $cashier = User::query()->whereIn('role', [Role::Accountant->value, Role::ChiefAccountant->value])->orderBy('id')->first();
        $pharmacist = User::query()->whereIn('role', [Role::Pharmacist->value, Role::ChiefPharmacist->value])->orderBy('id')->first();
        $labStaff = User::query()->where('role', Role::Lab->value)->orderBy('id')->first();
        $tests = LabTest::active()->orderBy('id')->get();
        $drugs = Drug::active()->orderBy('id')->get();

        if ($departments->count() !== 3 || ! $receptionist || ! $cashier || ! $pharmacist || ! $labStaff || $tests->isEmpty() || $drugs->isEmpty()) {
            throw new RuntimeException('Hospital 10 is missing a required department, staff role, laboratory test, or medicine catalog entry.');
        }

        foreach ($departments as $department) {
            if (($doctors[$department->id] ?? collect())->isEmpty() || ($nurses[$department->id] ?? collect())->isEmpty()) {
                throw new RuntimeException("Hospital 10 needs an assigned doctor and nurse in {$department->name}.");
            }
            if (! ConsultationType::active()->where('department_id', $department->id)->exists()) {
                throw new RuntimeException("Hospital 10 has no consultation pricing for {$department->name}.");
            }
        }

        $billing = app(BillingService::class);
        $labWorkflow = app(LabWorkflowService::class);
        $prescriptions = app(PrescriptionService::class);
        $pharmacy = app(PharmacyService::class);
        $inventory = app(DrugInventoryService::class);
        $nursing = app(MedicationAdministrationService::class);
        $notifications = app(WorkflowNotificationService::class);

        foreach ($drugs as $drug) {
            if (! $drug->movements()->exists()) {
                $inventory->recordOpeningBalance($drug);
            }
        }

        $encounterNumber = 0;
        $patientVisitCounts = $this->visitCounts();

        foreach ($patients as $patientIndex => $patient) {
            $count = $patientVisitCounts[$patientIndex] ?? 0;
            for ($visitIndex = 0; $visitIndex < $count; $visitIndex++) {
                $encounterNumber++;
                $department = $this->departmentFor($patient, $departments);
                $doctor = $doctors[$department->id]->first();
                $nurse = $nurses[$department->id]->first();
                $consultationType = ConsultationType::active()->where('department_id', $department->id)->orderBy('id')->firstOrFail();
                $checkedInAt = now()->subDays(7 + ($encounterNumber * 4))->setTime(8 + ($encounterNumber % 8), ($encounterNumber * 7) % 60);
                $appointment = null;

                // About one third of the encounters entered through appointments; the rest are walk-ins.
                if ($encounterNumber % 3 === 0) {
                    $appointment = Appointment::create([
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'type' => 'consultation',
                        'scheduled_at' => $checkedInAt,
                        'reason' => 'Outpatient consultation',
                        'status' => AppointmentStatus::Completed,
                        'created_by' => $receptionist->id,
                        'created_at' => $checkedInAt,
                        'updated_at' => $checkedInAt,
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
                    'checked_in_at' => $checkedInAt,
                    'consulted_at' => $checkedInAt->copy()->addMinutes(35),
                    'clinical_notes' => self::MARKER.' Assessment and care plan recorded by the attending clinician.',
                    'created_at' => $checkedInAt,
                    'updated_at' => $checkedInAt,
                ]);

                $visit->vitals()->create([
                    'recorded_by' => $nurse->id,
                    'temperature' => [36.7, 37.1, 37.4, 37.8][$encounterNumber % 4],
                    'pulse' => 68 + ($encounterNumber % 35),
                    'respiratory_rate' => 14 + ($encounterNumber % 7),
                    'bp_systolic' => 108 + ($encounterNumber % 32),
                    'bp_diastolic' => 68 + ($encounterNumber % 18),
                    'spo2' => 96 + ($encounterNumber % 4),
                    'weight' => 48 + ($encounterNumber % 42),
                    'height' => 148 + ($encounterNumber % 38),
                    'notes' => 'Routine outpatient triage observations.',
                    'created_at' => $checkedInAt,
                    'updated_at' => $checkedInAt,
                ]);

                $testIds = $this->testsForEncounter($encounterNumber, $tests);
                if ($testIds !== []) {
                    $labWorkflow->requestTests($visit, $testIds, $doctor->id);
                    LabRequest::query()->where('visit_id', $visit->id)->update([
                        'created_at' => $checkedInAt,
                        'updated_at' => $checkedInAt,
                    ]);
                }

                $prescription = $this->prescribeForEncounter($encounterNumber, $visit, $doctor, $drugs, $prescriptions, $checkedInAt);
                if ($prescription) {
                    $prescriptions->placeOrder($visit, $doctor->id);
                }

                $invoice = $billing->syncFor($visit);
                $invoice->update(['created_at' => $checkedInAt, 'updated_at' => $checkedInAt]);
                InvoiceItem::query()->where('invoice_id', $invoice->id)->update([
                    'created_at' => $checkedInAt,
                    'updated_at' => $checkedInAt,
                ]);

                // Most encounters are settled in full. A smaller set stays unpaid or
                // partially paid so the payment gates and pending queues can be exercised.
                $items = $invoice->items()->get();
                $settledItems = match ($encounterNumber % 10) {
                    0 => collect(),
                    5 => $items->where('category', 'consultation'),
                    default => $items,
                };
                $this->settleItems($invoice, $settledItems, $cashier, $encounterNumber, $checkedInAt);

                $visit->update([
                    'status' => VisitStatus::Completed,
                    'diagnosis' => $this->diagnosisFor($encounterNumber),
                    'clinical_notes' => self::MARKER.' Assessment completed. Treatment and follow-up advice were documented.',
                    'closed_at' => $checkedInAt->copy()->addHours(2),
                    'updated_at' => $checkedInAt->copy()->addHours(2),
                ]);

                foreach ($visit->labRequests()->get() as $request) {
                    if ($request->invoiceItem?->is_paid) {
                        $request->update([
                            'result' => 'Completed. Findings reviewed and filed by the laboratory scientist.',
                            'status' => 'completed',
                            'processed_by' => $labStaff->id,
                            'completed_at' => $checkedInAt->copy()->addHour(),
                            'updated_at' => $checkedInAt->copy()->addHour(),
                        ]);
                        $notifications->doctorsFor($visit, 'Laboratory result available', 'A paid laboratory investigation has been completed and is ready for clinical review.');
                    }
                }

                if ($prescription && $prescription->invoiceItem?->is_paid) {
                    $pharmacy->dispense($visit, [$prescription->id], $pharmacist->id);
                    $prescription->refresh();
                    if ($prescription->status->value === 'completed' && $prescription->requires_in_facility_administration) {
                        $administration = $nursing->record($visit, $prescription, [
                            'status' => 'administered',
                            'dose' => $prescription->dosage,
                            'route' => str_contains(strtolower($prescription->drug->form), 'inject') ? 'intramuscular' : 'oral',
                            'site' => str_contains(strtolower($prescription->drug->form), 'inject') ? 'left deltoid' : null,
                            'notes' => 'Identity, medication, dose, route, and time checked before administration.',
                        ], $nurse);
                        $administration->update([
                            'administered_at' => $checkedInAt->copy()->addHours(2),
                            'created_at' => $checkedInAt->copy()->addHours(2),
                            'updated_at' => $checkedInAt->copy()->addHours(2),
                        ]);
                    }
                }
            }
        }

        $this->seedUpcomingAppointments($patients, $departments, $doctors, $tests, $receptionist);
    }

    /** 8 patients have no history; the others have 2, 3, 5, or 1 visit respectively. */
    private function visitCounts(): array
    {
        return array_merge(array_fill(0, 8, 0), array_fill(0, 8, 2), array_fill(0, 8, 3), array_fill(0, 8, 5), array_fill(0, 8, 1));
    }

    private function departmentFor(Patient $patient, EloquentCollection $departments): Department
    {
        if (($patient->age ?? 30) < 17) {
            return $departments->get('Paediatrics');
        }

        if (strtolower((string) $patient->gender) === 'female' && ($patient->age ?? 30) >= 18 && ($patient->age ?? 30) <= 45 && $patient->id % 5 === 0) {
            return $departments->get('Maternity');
        }

        return $departments->get('General Medicine');
    }

    private function testsForEncounter(int $number, EloquentCollection $tests): array
    {
        if ($number % 4 !== 1 && $number % 7 !== 0) {
            return [];
        }

        $offset = $number % $tests->count();
        $selected = [$tests[$offset]->id];
        if ($number % 7 === 0 && $tests->count() > 1) {
            $selected[] = $tests[($offset + 1) % $tests->count()]->id;
        }

        return $selected;
    }

    private function prescribeForEncounter(int $number, Visit $visit, User $doctor, EloquentCollection $drugs, PrescriptionService $service, Carbon $encounterAt): ?Prescription
    {
        $administrationDrug = $number % 17 === 0
            ? $drugs->first(fn (Drug $drug) => str_contains(strtolower((string) $drug->form), 'inject') && $drug->available_quantity > 0)
            : null;
        $drug = $administrationDrug ?? $drugs->first(fn (Drug $candidate) => $candidate->available_quantity >= 4 && ! str_contains(strtolower((string) $candidate->form), 'inject'));

        if (! $drug || ($number % 3 !== 0 && ! $administrationDrug)) {
            return null;
        }

        $quantity = $administrationDrug ? 1 : 4;
        $result = $service->prescribe($visit, [
            'drug_id' => $drug->id,
            'dosage' => $administrationDrug ? '1 dose' : '1 tablet',
            'frequency' => $administrationDrug ? 'Once' : 'Twice daily',
            'duration' => $administrationDrug ? '1 day' : '2 days',
            'quantity' => $quantity,
            'instructions' => $administrationDrug ? 'Administer at the hospital after pharmacy dispensing.' : 'Take as directed after food.',
            'requires_in_facility_administration' => (bool) $administrationDrug,
            'required_administration_count' => 1,
            'created_at' => $encounterAt,
            'updated_at' => $encounterAt,
        ], $doctor->id);

        if ($result['error'] || ! isset($result['reserved_quantity'])) {
            return null;
        }

        return $visit->prescriptions()->latest('id')->first();
    }

    private function settleItems(Invoice $invoice, $items, User $cashier, int $number, Carbon $encounterAt): void
    {
        $items = $items->whereNull('payment_id');
        $amount = (float) $items->sum('total');
        if ($amount <= 0 || $items->isEmpty()) {
            return;
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'pos'][$number % 3],
            'reference' => 'MMH-DEMO-'.$number,
            'received_by' => $cashier->id,
            'created_at' => $encounterAt->copy()->addMinutes(45),
            'updated_at' => $encounterAt->copy()->addMinutes(45),
        ]);
        $invoice->items()->whereIn('id', $items->modelKeys())->update(['payment_id' => $payment->id]);
        $invoice->recalculate();
    }

    private function seedUpcomingAppointments(EloquentCollection $patients, EloquentCollection $departments, $doctors, EloquentCollection $tests, User $receptionist): void
    {
        for ($index = 0; $index < 8; $index++) {
            $patient = $patients[$index];
            $department = $this->departmentFor($patient, $departments);
            Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctors[$department->id]->first()->id,
                'type' => 'consultation',
                'scheduled_at' => now()->addDays($index + 1)->setTime(9 + ($index % 6), 0),
                'reason' => 'Booked outpatient consultation',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $receptionist->id,
            ]);
        }

        for ($index = 0; $index < 4; $index++) {
            $appointment = Appointment::create([
                'patient_id' => $patients[8 + $index]->id,
                'doctor_id' => null,
                'type' => 'lab',
                'scheduled_at' => now()->addDays($index + 2)->setTime(10 + $index, 0),
                'reason' => 'Booked laboratory investigations',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $receptionist->id,
            ]);
            $appointment->labTests()->sync($tests->slice($index, min(2, $tests->count()))->modelKeys());
        }
    }

    private function complaintFor(int $number): string
    {
        return [
            'Fever and general malaise',
            'Follow-up review for ongoing symptoms',
            'Intermittent headache',
            'Abdominal discomfort',
            'Routine health assessment',
            'Cough and nasal congestion',
        ][$number % 6];
    }

    private function diagnosisFor(int $number): string
    {
        return [
            'Acute febrile illness; stable for outpatient care.',
            'Symptoms improving; continue supportive care.',
            'Tension-type headache; outpatient management advised.',
            'Dyspepsia; lifestyle and treatment advice provided.',
            'No acute concerns identified at review.',
            'Upper respiratory tract infection; follow-up advised if symptoms worsen.',
        ][$number % 6];
    }
}
