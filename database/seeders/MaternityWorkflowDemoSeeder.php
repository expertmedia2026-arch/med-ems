<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\MaternityEpisode;
use App\Models\MaternityNewborn;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MaternityWorkflowDemoSeeder extends Seeder
{
    /** Set this to the hospital whose isolated test records should be created. */
    public int $hospitalId = 1;

    /**
     * Ten illustrative admission/delivery scenarios; the final case deliberately
     * remains in labor so the in-progress screen can also be explored.
     */
    private const CASES = [
        ['days_ago' => 90, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 01', 'gender' => 'female']]],
        ['days_ago' => 80, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 02', 'gender' => 'male']]],
        ['days_ago' => 70, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 03', 'gender' => 'female']]],
        ['days_ago' => 60, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 04', 'gender' => 'male']]],
        ['days_ago' => 50, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 05', 'gender' => 'female']]],
        ['days_ago' => 40, 'mode' => 'vaginal', 'babies' => [['name' => 'Demo Baby 06', 'gender' => 'male']]],
        ['days_ago' => 30, 'mode' => 'vaginal', 'babies' => [
            ['name' => 'Demo Baby 07A', 'gender' => 'female'],
            ['name' => 'Demo Baby 07B', 'gender' => 'male'],
        ]],
        ['days_ago' => 20, 'mode' => 'assisted_vaginal', 'babies' => [['name' => 'Demo Baby 08', 'gender' => 'female']]],
        ['days_ago' => 10, 'mode' => 'cesarean', 'babies' => [['name' => 'Demo Baby 09', 'gender' => 'male']]],
        ['days_ago' => null, 'mode' => null, 'babies' => []],
    ];

    /** Synthetic names are used so this seeder never creates real patient identities. */
    private const MOTHER_NAMES = [
        ['Amina', 'Demo'],
        ['Grace', 'Example'],
        ['Nneka', 'Sample'],
        ['Fatima', 'Demo'],
        ['Ada', 'Example'],
        ['Zainab', 'Sample'],
        ['Ifeoma', 'Demo'],
        ['Mariam', 'Example'],
        ['Chiamaka', 'Sample'],
        ['Esther', 'Demo'],
    ];

    /**
     * Seed one hospital only, preserving the caller's tenant context and
     * skipping the operation when its demo marker already exists.
     */
    public function run(): void
    {
        $hospital = Hospital::query()->find($this->hospitalId);
        if (! $hospital) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} does not exist.");
        }

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set($this->hospitalId);

        try {
            $this->assertHospitalReady($hospital);
            $marker = "[MATERNITY_DEMO_HOSPITAL_{$this->hospitalId}]";

            // Seed related patients, visits, admissions, and episodes as one
            // unit so an interrupted run does not leave a partial demo cohort.
            $seeded = DB::transaction(function () use ($marker): bool {
                // The marker makes a repeated run a safe no-op rather than
                // creating duplicate visits, admissions, and newborns.
                if (Visit::query()->where('clinical_notes', 'like', '%'.$marker.'%')->exists()) {
                    return false;
                }

                $this->seedCases($marker);

                return true;
            });
        } finally {
            $context->set($previousHospitalId);
        }

        if (! $seeded) {
            $this->command?->info("Maternity demo records already exist for hospital ID {$this->hospitalId}; no changes made.");

            return;
        }

        $this->command?->info("Seeded 10 synthetic maternity cases for hospital ID {$this->hospitalId}.");
        $this->command?->info('Each case includes two completed antenatal consultations; nine have delivery records and one remains in labor.');
    }

    /** Fail early with an actionable message when essential hospital setup is missing. */
    private function assertHospitalReady(Hospital $hospital): void
    {
        if (! $hospital->hasModule('inpatient') || ! $hospital->hasModule('maternity')) {
            throw new RuntimeException("Enable both Inpatient and Maternity for hospital ID {$this->hospitalId} before running this seeder.");
        }

        $antenatalType = ConsultationType::query()
            ->where('name', 'Antenatal Consultation')
            ->where('is_active', true)
            ->first();

        if (! $antenatalType) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} needs an active Antenatal Consultation type before running this seeder.");
        }

        $antenatalDoctor = User::query()
            ->where('role', Role::Doctor->value)
            ->where('department_id', $antenatalType->department_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $antenatalDoctor) {
            throw new RuntimeException('Assign an active doctor to the Antenatal Consultation department before running this seeder.');
        }

        if (! Department::query()->where('has_ward', true)->exists()) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} needs at least one inpatient ward before running this seeder.");
        }
    }

    /**
     * Create the ten related maternal patients, antenatal visits, admissions,
     * episodes, and any delivered newborns for the selected tenant.
     */
    private function seedCases(string $marker): void
    {
        $antenatalType = ConsultationType::query()
            ->where('name', 'Antenatal Consultation')
            ->where('is_active', true)
            ->firstOrFail();
        $antenatalDepartment = $antenatalType->department;
        $antenatalDoctor = User::query()
            ->where('role', Role::Doctor->value)
            ->where('department_id', $antenatalDepartment->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();
        $wards = Department::query()->where('has_ward', true)->orderBy('id')->get();
        // Prefer a clearly named maternity ward but support hospitals that
        // have only a general inpatient ward configured.
        $maternityWard = $wards->first(
            fn (Department $department): bool => Str::contains(Str::lower($department->name), ['maternity', 'obstetric', 'gyn']),
        ) ?? $wards->first();
        $attendingDoctor = User::query()
            ->where('role', Role::Doctor->value)
            ->where('department_id', $maternityWard->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first() ?? $antenatalDoctor;

        foreach (self::CASES as $index => $case) {
            $caseNumber = $index + 1;
            [$firstName, $lastName] = self::MOTHER_NAMES[$index];
            $mother = Patient::create([
                'hospital_number' => sprintf('MAT-DEMO-%d-%02d', $this->hospitalId, $caseNumber),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => 'female',
                'date_of_birth' => now()->subYears(22 + ($index % 12))->toDateString(),
            ]);

            $deliveryAt = $case['days_ago'] === null
                ? null
                : now()->subDays($case['days_ago'])->setTime(8 + ($index % 5), 15);
            $laborStartedAt = $deliveryAt?->copy()->subHours(6) ?? now()->subHours(4);
            $this->seedAntenatalVisits($mother, $antenatalType, $antenatalDoctor, $laborStartedAt, $marker, $caseNumber);

            $admissionVisit = Visit::create([
                'patient_id' => $mother->id,
                'doctor_id' => $attendingDoctor->id,
                'department_id' => $maternityWard->id,
                'type' => 'consultation',
                'status' => $deliveryAt ? VisitStatus::Completed : VisitStatus::Inpatient,
                'complaint' => 'Admission for labor and birth (synthetic demo record).',
                'diagnosis' => $deliveryAt ? 'Post-delivery inpatient care (demo record).' : 'Labor assessment in progress (demo record).',
                'clinical_notes' => "{$marker} Labor and admission record for workflow demonstration only.",
                'checked_in_at' => $laborStartedAt,
                'consulted_at' => $laborStartedAt->copy()->addHour(),
                'closed_at' => $deliveryAt?->copy()->addDays(2),
                'created_at' => $laborStartedAt,
                'updated_at' => $laborStartedAt,
            ]);

            // Historical birth cases have discharged admissions; the last
            // case stays active so the labor workflow remains interactive.
            $dischargedAt = $deliveryAt?->copy()->addDays(2);
            $admission = Admission::create([
                'admission_number' => sprintf('MAT-DEMO-%d-ADM-%02d', $this->hospitalId, $caseNumber),
                'visit_id' => $admissionVisit->id,
                'patient_id' => $mother->id,
                'department_id' => $maternityWard->id,
                'attending_doctor_id' => $attendingDoctor->id,
                'admitted_by' => $attendingDoctor->id,
                'status' => $deliveryAt ? AdmissionStatus::Discharged : AdmissionStatus::Active,
                'admitted_at' => $laborStartedAt,
                'expected_discharge_date' => $dischargedAt?->toDateString(),
                'discharged_by' => $dischargedAt ? $attendingDoctor->id : null,
                'discharged_at' => $dischargedAt,
                'admission_reason' => 'Labor and delivery (synthetic demo record).',
                'admission_diagnosis' => 'Maternity admission for labor (synthetic demo record).',
                'initial_treatment_plan' => 'Document and provide care according to the responsible clinician and hospital protocol.',
                'discharge_diagnosis' => $dischargedAt ? 'Post-delivery discharge (synthetic demo record).' : null,
                'discharge_summary' => $dischargedAt ? 'Synthetic historical case for demonstrating the maternity workflow.' : null,
                'follow_up_instructions' => $dischargedAt ? 'Follow the plan documented by the responsible maternity clinician.' : null,
                'patient_discharge_instructions' => $dischargedAt ? 'Synthetic demo record; not patient-specific guidance.' : null,
                'created_at' => $laborStartedAt,
                'updated_at' => $laborStartedAt,
            ]);

            $episode = MaternityEpisode::create([
                'admission_id' => $admission->id,
                'created_by' => $attendingDoctor->id,
                'status' => $deliveryAt ? 'postpartum' : 'in_labor',
                'labor_started_at' => $laborStartedAt,
                'labor_assessment' => "{$marker} Illustrative labor assessment only; not clinical guidance.",
                'patient_brought_delivery_materials' => $caseNumber % 2 === 0,
                'delivery_at' => $deliveryAt,
                'delivery_mode' => $case['mode'],
                'delivery_notes' => $deliveryAt
                    ? 'Synthetic delivery record for workflow demonstration; not a clinical guideline.'
                    : null,
                'hospital_supplied_delivery_pack' => $deliveryAt ? $caseNumber % 2 === 1 : null,
                'delivery_material_notes' => $deliveryAt
                    ? 'Synthetic delivery materials record for workflow demonstration.'
                    : null,
                'postpartum_notes' => $deliveryAt
                    ? 'Synthetic postpartum follow-up entry; document actual care with the responsible clinician.'
                    : null,
                'created_at' => $laborStartedAt,
                'updated_at' => $laborStartedAt,
            ]);

            if ($deliveryAt) {
                $this->seedNewborns($episode, $case['babies'], $deliveryAt, $caseNumber);
            }
        }
    }

    /**
     * Add two completed visits using the hospital's existing Antenatal
     * Consultation type; antenatal care remains part of the ordinary visit history.
     */
    private function seedAntenatalVisits(
        Patient $mother,
        ConsultationType $antenatalType,
        User $doctor,
        Carbon $laborStartedAt,
        string $marker,
        int $caseNumber,
    ): void {
        foreach ([120, 60] as $visitNumber => $daysBeforeLabor) {
            $checkedInAt = $laborStartedAt->copy()->subDays($daysBeforeLabor)->setTime(9 + $visitNumber, 0);

            Visit::create([
                'patient_id' => $mother->id,
                'doctor_id' => $doctor->id,
                'department_id' => $antenatalType->department_id,
                'consultation_type_id' => $antenatalType->id,
                'type' => 'consultation',
                'status' => VisitStatus::Completed,
                'complaint' => 'Routine antenatal follow-up (synthetic demo record).',
                'diagnosis' => 'Antenatal consultation completed (synthetic demo record).',
                'clinical_notes' => "{$marker} Case {$caseNumber}, antenatal visit ".($visitNumber + 1).'; synthetic example only.',
                'checked_in_at' => $checkedInAt,
                'consulted_at' => $checkedInAt->copy()->addMinutes(30),
                'closed_at' => $checkedInAt->copy()->addHour(),
                'created_at' => $checkedInAt,
                'updated_at' => $checkedInAt,
            ]);
        }
    }

    /**
     * Create a patient and birth record for every synthetic live-born child.
     *
     * @param  array<int, array{name: string, gender: string}>  $babies
     */
    private function seedNewborns(
        MaternityEpisode $episode,
        array $babies,
        Carbon $deliveryAt,
        int $caseNumber,
    ): void {
        foreach ($babies as $index => $baby) {
            [$firstName, $lastName] = explode(' ', $baby['name'], 2);
            $birthOrder = $index + 1;
            $patient = Patient::create([
                'hospital_number' => sprintf('MAT-DEMO-%d-BABY-%02d-%d', $this->hospitalId, $caseNumber, $birthOrder),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $baby['gender'],
                'date_of_birth' => $deliveryAt->toDateString(),
            ]);

            MaternityNewborn::create([
                'maternity_episode_id' => $episode->id,
                'patient_id' => $patient->id,
                'birth_order' => $birthOrder,
                'outcome' => 'live_birth',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $baby['gender'],
                'date_of_birth' => $deliveryAt->toDateString(),
            ]);
        }
    }
}
