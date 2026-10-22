<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Admission;
use App\Models\Department;
use App\Models\Drug;
use App\Models\EmergencyObservation;
use App\Models\EmergencyProgressNote;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\MortuaryCaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmergencyMortuarySeeder extends Seeder
{
    public function run(): void
    {
        $emergency = Department::firstOrCreate(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.'],
        );
        $ward = Department::firstOrCreate(
            ['name' => 'General Medicine'],
            ['description' => 'Outpatient assessment and general medical care.', 'has_ward' => true],
        );
        $ward->update(['has_ward' => true]);

        $doctor = $this->staff('er.doctor@hms.test', 'Dr. ER Demo', Role::Doctor, $emergency);
        $nurse = $this->staff('er.nurse@hms.test', 'Nurse ER Demo', Role::Nurse, $emergency);
        $mortuaryAttendant = $this->staff('mortuary@hms.test', 'Mortuary Attendant Demo', Role::MortuaryAttendant);
        $receptionist = User::firstOrCreate(
            ['email' => 'reception@hms.test'],
            ['name' => 'Demo Receptionist', 'role' => Role::Receptionist, 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()],
        );
        $wardDoctor = User::firstOrCreate(
            ['email' => 'doctor@hms.test'],
            ['name' => 'Dr. General Medicine Demo', 'role' => Role::Doctor, 'department_id' => $ward->id, 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()],
        );
        $drug = Drug::firstOrCreate(
            ['name' => 'Artemether Injection', 'strength' => '80mg/ml'],
            ['generic_name' => 'Artemether', 'form' => 'Injection', 'unit_price' => 2200, 'stock_quantity' => 6, 'is_active' => true],
        );
        $test = LabTest::firstOrCreate(
            ['name' => 'Full blood count (FBC)'],
            ['code' => 'LAB002', 'category' => 'Hematology', 'sample_type' => 'Blood', 'turnaround_hours' => 8, 'description' => 'Full blood count.', 'price' => 4000, 'is_active' => true],
        );

        DB::transaction(function () use ($emergency, $ward, $doctor, $nurse, $wardDoctor, $receptionist, $mortuaryAttendant, $drug, $test) {

            $specs = [
                ['key' => 'triage', 'first' => 'ER Demo', 'last' => 'Needs Triage', 'status' => VisitStatus::WaitingTriage, 'priority' => null, 'elapsed' => 12],
                ['key' => 'doctor-queue', 'first' => 'ER Demo', 'last' => 'Doctor Queue', 'status' => VisitStatus::WaitingDoctor, 'priority' => 'high', 'elapsed' => 38],
                ['key' => 'active-care', 'first' => 'ER Demo', 'last' => 'Active Care', 'status' => VisitStatus::InConsultation, 'priority' => 'moderate', 'elapsed' => 95],
                ['key' => 'admitted', 'first' => 'ER Demo', 'last' => 'Inpatient Transfer', 'status' => VisitStatus::Inpatient, 'priority' => 'high', 'elapsed' => 310],
                ['key' => 'discharged', 'first' => 'ER Demo', 'last' => 'Discharged', 'status' => VisitStatus::Completed, 'priority' => 'low', 'elapsed' => 470, 'disposition' => 'discharged'],
                ['key' => 'mortuary-awaiting', 'first' => 'Mortuary Demo', 'last' => 'Awaiting Transfer', 'status' => VisitStatus::Completed, 'priority' => 'critical', 'elapsed' => 205, 'disposition' => 'deceased', 'mortuary' => 'awaiting_transfer'],
                ['key' => 'mortuary-received', 'first' => 'Mortuary Demo', 'last' => 'In Storage', 'status' => VisitStatus::Completed, 'priority' => 'critical', 'elapsed' => 610, 'disposition' => 'deceased', 'mortuary' => 'received'],
                ['key' => 'mortuary-released', 'first' => 'Mortuary Demo', 'last' => 'Released', 'status' => VisitStatus::Completed, 'priority' => 'critical', 'elapsed' => 990, 'disposition' => 'deceased', 'mortuary' => 'released'],
            ];

            foreach ($specs as $index => $spec) {
                $patient = $this->patient($spec['key'], $spec['first'], $spec['last'], $index);
                $checkedInAt = now()->subMinutes($spec['elapsed']);
                $hasBeenTriaged = $spec['status'] !== VisitStatus::WaitingTriage;
                $hasDoctor = in_array($spec['status'], [VisitStatus::InConsultation, VisitStatus::Inpatient, VisitStatus::Completed], true);
                $isClosed = $spec['status'] === VisitStatus::Completed || $spec['status'] === VisitStatus::Inpatient;
                $deceasedAt = ! empty($spec['disposition']) && $spec['disposition'] === 'deceased'
                    ? $checkedInAt->copy()->addMinutes(54)
                    : null;

                $visit = Visit::updateOrCreate(
                    ['patient_id' => $patient->id, 'type' => 'consultation', 'is_emergency' => true],
                    [
                        'department_id' => $emergency->id,
                        'registered_by' => $receptionist->id,
                        'doctor_id' => $hasDoctor ? $doctor->id : null,
                        'status' => $spec['status'],
                        'arrival_mode' => $index % 2 === 0 ? 'ambulance' : 'walk_in',
                        'triage_priority' => $spec['priority'],
                        'triaged_at' => $hasBeenTriaged ? $checkedInAt->copy()->addMinutes(12) : null,
                        'complaint' => 'DEMO ER workflow: '.strtolower($spec['last']).'.',
                        'diagnosis' => $hasDoctor ? 'Demo assessment recorded for ER workflow.' : null,
                        'clinical_notes' => $hasDoctor ? 'Seeded demo record. Use this visit to review the ER workflow screens.' : null,
                        'checked_in_at' => $checkedInAt,
                        'consulted_at' => $hasDoctor ? $checkedInAt->copy()->addMinutes(20) : null,
                        'closed_at' => $isClosed ? $checkedInAt->copy()->addMinutes(54) : null,
                        'disposition' => $spec['disposition'] ?? ($spec['status'] === VisitStatus::Inpatient ? 'admitted' : null),
                        'disposition_note' => ($spec['disposition'] ?? null) === 'deceased' ? 'Seeded demo mortuary handoff.' : null,
                    ],
                );

                if ($hasBeenTriaged) {
                    $this->seedVitals($visit, $nurse, $checkedInAt);
                }

                if ($spec['status'] === VisitStatus::InConsultation) {
                    $this->seedActiveTreatment($visit, $doctor, $drug, $test);
                    $this->seedNotes($visit, $doctor, $nurse);
                    app(BillingService::class)->syncFor($visit);
                }

                if ($spec['status'] === VisitStatus::Inpatient) {
                    $admission = Admission::updateOrCreate(
                        ['visit_id' => $visit->id],
                        [
                            'patient_id' => $patient->id,
                            'department_id' => $ward->id,
                            'attending_doctor_id' => $wardDoctor->id,
                            'admitted_by' => $doctor->id,
                            'status' => AdmissionStatus::Active,
                            'admitted_at' => $checkedInAt->copy()->addMinutes(54),
                            'admission_reason' => 'Seeded ER demo transfer to inpatient care.',
                            'admission_diagnosis' => 'Demo diagnosis requiring inpatient observation.',
                            'initial_treatment_plan' => 'Continue observation and treatment on the General Medicine ward.',
                            'expected_discharge_date' => now()->addDays(2)->toDateString(),
                        ],
                    );
                    if (! $admission->admission_number) {
                        $admission->update(['admission_number' => 'ADM-'.now()->format('Ymd').'-'.str_pad((string) $admission->id, 6, '0', STR_PAD_LEFT)]);
                    }
                    app(BillingService::class)->syncFor($visit);
                }

                if (($spec['disposition'] ?? null) === 'deceased') {
                    $case = app(MortuaryCaseService::class)->openFromEmergency($visit, $doctor, $deceasedAt?->toDateTimeString());
                    $this->advanceMortuaryCase($case, $spec['mortuary'], $mortuaryAttendant, $index);
                }
            }

            if (! MortuaryCase::where('body_tag_number', 'DEMO-WALKIN-0001')->exists()) {
                app(MortuaryCaseService::class)->createWalkIn([
                    'first_name' => 'Walk-in Demo',
                    'last_name' => 'Mortuary Case',
                    'gender' => 'unknown',
                    'estimated_age' => 46,
                    'deceased_at' => now()->subDay()->toDateTimeString(),
                    'external_contact_name' => 'Demo External Contact',
                    'external_contact_phone' => '08000000001',
                    'external_contact_address' => 'Demo address, Enugu',
                    'storage_location' => 'Cold room C · Rack 01',
                    'body_tag_number' => 'DEMO-WALKIN-0001',
                    'handoff_note' => 'DEMO: External walk-in intake and billing workflow.',
                ], $mortuaryAttendant);
            }
        });
    }

    private function staff(string $email, string $name, Role $role, ?Department $department = null): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'department_id' => $department?->id,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    private function patient(string $key, string $firstName, string $lastName, int $index): Patient
    {
        return Patient::updateOrCreate(
            ['email' => 'demo.'.$key.'@hms.test'],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'date_of_birth' => now()->subYears(30 + $index * 4)->toDateString(),
                'phone' => '080'.str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT),
                'address' => 'Demo address, Enugu',
                'next_of_kin_name' => 'Demo Next of Kin '.$index,
                'next_of_kin_phone' => '080'.str_pad((string) (80000000 + $index), 8, '0', STR_PAD_LEFT),
                'identity_status' => 'identified',
            ],
        );
    }

    private function seedVitals(Visit $visit, User $nurse, $checkedInAt): void
    {
        $visit->vitals()->updateOrCreate(
            ['visit_id' => $visit->id],
            [
                'recorded_by' => $nurse->id,
                'temperature' => 38.2,
                'pulse' => 106,
                'respiratory_rate' => 22,
                'bp_systolic' => 108,
                'bp_diastolic' => 70,
                'spo2' => 95,
                'weight' => 68,
                'height' => 170,
                'notes' => 'DEMO: Initial ER assessment; continue repeat observations.',
            ],
        );

        EmergencyObservation::where('visit_id', $visit->id)->delete();
        foreach ([0, 15] as $round) {
            EmergencyObservation::create([
                'visit_id' => $visit->id,
                'recorded_by' => $nurse->id,
                'recorded_at' => $checkedInAt->copy()->addMinutes(10 + $round),
                'temperature' => $round ? 38.0 : 38.2,
                'pulse' => $round ? 102 : 106,
                'respiratory_rate' => 22,
                'bp_systolic' => 108,
                'bp_diastolic' => 70,
                'spo2' => 95,
                'notes' => $round ? 'DEMO: Repeat observation after initial treatment.' : 'DEMO: Initial triage observation.',
            ]);
        }
    }

    private function seedActiveTreatment(Visit $visit, User $doctor, Drug $drug, LabTest $test): void
    {
        Prescription::firstOrCreate(
            ['visit_id' => $visit->id, 'instructions' => 'DEMO ER order: record nursing administration.'],
            [
                'drug_id' => $drug->id,
                'prescribed_by' => $doctor->id,
                'dosage' => '80 mg',
                'frequency' => 'Once, then review',
                'duration' => 'One dose',
                'quantity' => 1,
                'dispensable_quantity' => 1,
                'status' => WorkStatus::Pending,
                'requires_in_facility_administration' => true,
                'required_administration_count' => 1,
            ],
        );

        LabRequest::firstOrCreate(
            ['visit_id' => $visit->id, 'lab_test_id' => $test->id],
            ['requested_by' => $doctor->id, 'status' => WorkStatus::Pending],
        );
    }

    private function seedNotes(Visit $visit, User $doctor, User $nurse): void
    {
        EmergencyProgressNote::where('visit_id', $visit->id)->delete();
        EmergencyProgressNote::create([
            'visit_id' => $visit->id,
            'author_id' => $nurse->id,
            'note_type' => 'nursing',
            'note' => 'DEMO: Patient monitored in ER; repeat observations documented.',
        ]);
        EmergencyProgressNote::create([
            'visit_id' => $visit->id,
            'author_id' => $doctor->id,
            'note_type' => 'medical',
            'note' => 'DEMO: Assessment in progress. Review investigation and nursing administration.',
        ]);
    }

    private function advanceMortuaryCase(MortuaryCase $case, string $targetStatus, User $attendant, int $index): void
    {
        $service = app(MortuaryCaseService::class);
        $tagNumber = 'DEMO-TAG-'.$index;
        $location = 'Cold room A · Rack '.str_pad((string) $index, 2, '0', STR_PAD_LEFT);

        if ($case->status === 'awaiting_transfer' && in_array($targetStatus, ['received', 'released'], true)) {
            $case = $service->receive($case, [
                'storage_location' => $location,
                'body_tag_number' => $tagNumber,
                'handoff_note' => 'DEMO: Body identity and ER handoff checked on receipt.',
            ], $attendant);
        }

        if ($case->status === 'received' && $targetStatus === 'received'
            && $case->storage_location === $location) {
            $service->move($case, [
                'storage_location' => 'Cold room B · Rack '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'note' => 'DEMO: Moved to the assigned long-term storage rack.',
            ], $attendant);
        }

        if ($case->status === 'received' && $targetStatus === 'released') {
            $service->release($case, [
                'released_to' => 'Demo Next of Kin '.$index,
                'recipient_relationship' => 'Next of kin',
                'recipient_id_reference' => 'DEMO-ID-'.$index,
                'release_authority' => 'DEMO-AUTH-'.$index,
                'release_note' => 'DEMO: Identity and authorization checked before release.',
            ], $attendant);
        }
    }
}
