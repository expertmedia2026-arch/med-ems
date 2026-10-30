<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\MaternityEpisode;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\MaternityService;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds a deterministic ten-patient maternity cohort for one hospital.
 *
 * The cases span antenatal attendance, active labor, delivery modes, newborn
 * outcomes, postpartum care, invoices, and payment states. Demo markers make
 * refresh idempotent without deleting genuine hospital records.
 */
class MaternityWorkflowDemoSeeder extends Seeder
{
    /** The command may replace this value to seed another hospital safely. */
    public int $hospitalId = 1;

    /** Replace this hospital's existing synthetic cohort before rebuilding it. */
    public bool $refreshExisting = false;

    /** Ten synthetic journeys covering different booking, outcome, payment, and workflow states. */
    private const CASES = [
        [
            'label' => 'Well-booked routine vaginal delivery',
            'antenatal_visits' => 8,
            'days_ago' => 120,
            'mode' => 'vaginal',
            'hospital_pack' => false,
            'payment_profile' => 'paid',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Amara Demo', 'gender' => 'female', 'weight' => 3250, 'apgar_1' => 8, 'apgar_5' => 9],
            ],
        ],
        [
            'label' => 'Late-booked mother currently in labor',
            'antenatal_visits' => 1,
            'days_ago' => null,
            'mode' => null,
            'hospital_pack' => null,
            'payment_profile' => 'unpaid',
            'keep_active' => true,
            'babies' => [],
        ],
        [
            'label' => 'Assisted vaginal delivery',
            'antenatal_visits' => 4,
            'days_ago' => 80,
            'mode' => 'assisted_vaginal',
            'hospital_pack' => true,
            'payment_profile' => 'mixed',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'David Example', 'gender' => 'male', 'weight' => 2980, 'apgar_1' => 7, 'apgar_5' => 9],
            ],
        ],
        [
            'label' => 'Planned cesarean delivery',
            'antenatal_visits' => 7,
            'days_ago' => 65,
            'mode' => 'cesarean',
            'hospital_pack' => true,
            'payment_profile' => 'paid',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Sofia Sample', 'gender' => 'female', 'weight' => 3420, 'apgar_1' => 8, 'apgar_5' => 10],
            ],
        ],
        [
            'label' => 'Emergency cesarean with active postpartum care',
            'antenatal_visits' => 2,
            'days_ago' => 3,
            'mode' => 'cesarean',
            'hospital_pack' => true,
            'payment_profile' => 'unpaid',
            'keep_active' => true,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Samuel Demo', 'gender' => 'male', 'weight' => 2610, 'apgar_1' => 6, 'apgar_5' => 8],
            ],
        ],
        [
            'label' => 'Vaginal twin delivery',
            'antenatal_visits' => 6,
            'days_ago' => 50,
            'mode' => 'vaginal',
            'hospital_pack' => true,
            'payment_profile' => 'mixed',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Zara Example', 'gender' => 'female', 'weight' => 2440, 'apgar_1' => 7, 'apgar_5' => 9],
                ['outcome' => 'live_birth', 'name' => 'Zain Example', 'gender' => 'male', 'weight' => 2370, 'apgar_1' => 7, 'apgar_5' => 9],
            ],
        ],
        [
            'label' => 'Cesarean twin delivery with mixed outcome',
            'antenatal_visits' => 5,
            'days_ago' => 40,
            'mode' => 'cesarean',
            'hospital_pack' => true,
            'payment_profile' => 'paid',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Ife Demo', 'gender' => 'female', 'weight' => 2280, 'apgar_1' => 7, 'apgar_5' => 8],
                ['outcome' => 'stillbirth', 'name' => null, 'gender' => null, 'weight' => 2050, 'apgar_1' => null, 'apgar_5' => null],
            ],
        ],
        [
            'label' => 'Stillbirth after limited antenatal attendance',
            'antenatal_visits' => 3,
            'days_ago' => 30,
            'mode' => 'vaginal',
            'hospital_pack' => false,
            'payment_profile' => 'unpaid',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'stillbirth', 'name' => null, 'gender' => null, 'weight' => 2180, 'apgar_1' => null, 'apgar_5' => null],
            ],
        ],
        [
            'label' => 'Other documented delivery mode',
            'antenatal_visits' => 4,
            'days_ago' => 18,
            'mode' => 'other',
            'hospital_pack' => false,
            'payment_profile' => 'paid',
            'keep_active' => false,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Noah Sample', 'gender' => 'male', 'weight' => 3100, 'apgar_1' => 8, 'apgar_5' => 9],
            ],
        ],
        [
            'label' => 'Recent vaginal delivery with active postpartum care',
            'antenatal_visits' => 6,
            'days_ago' => 1,
            'mode' => 'vaginal',
            'hospital_pack' => true,
            'payment_profile' => 'mixed',
            'keep_active' => true,
            'babies' => [
                ['outcome' => 'live_birth', 'name' => 'Chidi Example', 'gender' => 'male', 'weight' => 3340, 'apgar_1' => 8, 'apgar_5' => 9],
            ],
        ],
    ];

    /** Synthetic identities make every seeded record unmistakably non-production data. */
    private const MOTHER_NAMES = [
        ['Amina', 'Maternity Demo'],
        ['Grace', 'Maternity Example'],
        ['Nneka', 'Maternity Sample'],
        ['Fatima', 'Maternity Demo'],
        ['Ada', 'Maternity Example'],
        ['Zainab', 'Maternity Sample'],
        ['Ifeoma', 'Maternity Demo'],
        ['Mariam', 'Maternity Example'],
        ['Chiamaka', 'Maternity Sample'],
        ['Esther', 'Maternity Demo'],
    ];

    /** Seed the selected hospital atomically and make repeat runs a safe no-op. */
    public function run(BillingService $billing, MaternityService $maternity): void
    {
        $hospital = Hospital::query()->find($this->hospitalId);
        if (! $hospital) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} does not exist.");
        }

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set($this->hospitalId);

        try {
            $resources = $this->hospitalResources($hospital);
            $marker = "[MATERNITY_DEMO_HOSPITAL_{$this->hospitalId}]";

            $seeded = DB::transaction(function () use ($billing, $maternity, $resources, $marker): bool {
                if (Visit::query()->where('clinical_notes', 'like', '%'.$marker.'%')->exists()) {
                    if (! $this->refreshExisting) {
                        return false;
                    }

                    $this->deleteExistingDemoCohort($marker);
                }

                $this->ensureDemoPricing($resources['antenatal_type']);
                $this->seedCases($billing, $maternity, $resources, $marker);

                return true;
            });
        } finally {
            $context->set($previousHospitalId);
        }

        if (! $seeded) {
            $this->command?->info("Maternity demo records already exist for hospital ID {$this->hospitalId}; no changes made.");

            return;
        }

        $this->command?->info("Seeded 10 professional maternity workflow cases for hospital ID {$this->hospitalId}.");
        $this->command?->info('The cohort includes 46 antenatal visits, paid/unpaid/partial billing, nine deliveries, 11 birth records, and three active admissions.');
    }

    /** Delete only marker-linked synthetic mothers, their newborns, and cascading workflow data. */
    private function deleteExistingDemoCohort(string $marker): void
    {
        $markedPatientIds = Visit::query()
            ->where('clinical_notes', 'like', '%'.$marker.'%')
            ->pluck('patient_id')
            ->unique();
        $motherIds = Patient::query()
            ->whereIn('id', $markedPatientIds)
            ->whereLike('hospital_number', "MAT-DEMO-{$this->hospitalId}-%")
            ->pluck('id');
        $episodeIds = MaternityEpisode::query()
            ->whereHas('admission', fn ($admissions) => $admissions->whereIn('patient_id', $motherIds))
            ->pluck('id');
        $newbornPatientIds = DB::table('maternity_newborns')
            ->whereIn('maternity_episode_id', $episodeIds)
            ->whereNotNull('patient_id')
            ->pluck('patient_id');

        DB::table('maternity_newborns')->whereIn('maternity_episode_id', $episodeIds)->delete();
        Patient::query()->whereIn('id', $newbornPatientIds)->delete();
        Patient::query()->whereIn('id', $motherIds)->delete();
    }

    /**
     * Resolve and validate the selected hospital's clinical and billing staff.
     *
     * @return array{antenatal_type: ConsultationType, antenatal_doctor: User, maternity_ward: Department, attending_doctor: User, cashier: User}
     */
    private function hospitalResources(Hospital $hospital): array
    {
        if (! $hospital->hasModule('inpatient') || ! $hospital->hasModule('maternity')) {
            throw new RuntimeException("Enable both Inpatient and Maternity for hospital ID {$this->hospitalId} before running this seeder.");
        }

        $antenatalType = ConsultationType::query()
            ->where('is_active', true)
            ->whereLike('name', '%antenatal%')
            ->orderBy('id')
            ->first();
        if (! $antenatalType) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} needs an active consultation type containing 'Antenatal' before running this seeder.");
        }

        $antenatalDoctor = User::query()
            ->where('role', Role::Doctor->value)
            ->where('department_id', $antenatalType->department_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        if (! $antenatalDoctor) {
            throw new RuntimeException('Assign an active doctor to the antenatal consultation department before running this seeder.');
        }

        $wards = Department::query()->where('has_ward', true)->orderBy('id')->get();
        $maternityWard = $wards->first(
            fn (Department $department): bool => Str::contains(Str::lower($department->name), ['maternity', 'obstetric', 'gyn']),
        ) ?? $wards->first();
        if (! $maternityWard) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} needs at least one inpatient ward before running this seeder.");
        }

        $attendingDoctor = User::query()
            ->where('role', Role::Doctor->value)
            ->where('department_id', $maternityWard->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first() ?? $antenatalDoctor;
        $cashier = User::query()
            ->where('is_active', true)
            ->whereIn('role', [Role::Accountant->value, Role::ChiefAccountant->value, Role::Admin->value])
            ->orderBy('id')
            ->first();
        if (! $cashier) {
            throw new RuntimeException('Create an active accountant, chief accountant, or administrator before seeding maternity payments.');
        }

        return [
            'antenatal_type' => $antenatalType,
            'antenatal_doctor' => $antenatalDoctor,
            'maternity_ward' => $maternityWard,
            'attending_doctor' => $attendingDoctor,
            'cashier' => $cashier,
        ];
    }

    /** Preserve configured prices and supply realistic defaults only for zero-priced demo services. */
    private function ensureDemoPricing(ConsultationType $antenatalType): void
    {
        if ((float) $antenatalType->price <= 0) {
            $antenatalType->update(['price' => 6000]);
        }

        $settings = HospitalSetting::current();
        $defaults = [
            'maternity_vaginal_delivery_fee' => 25000,
            'maternity_assisted_delivery_fee' => 35000,
            'maternity_cesarean_delivery_fee' => 120000,
            'maternity_other_delivery_fee' => 40000,
            'maternity_delivery_pack_fee' => 15000,
        ];

        $settings->update(collect($defaults)
            ->filter(fn (int $amount, string $field): bool => (float) $settings->{$field} <= 0)
            ->all());
    }

    /**
     * Create the maternal patients and run each case through the application's real services.
     *
     * @param  array{antenatal_type: ConsultationType, antenatal_doctor: User, maternity_ward: Department, attending_doctor: User, cashier: User}  $resources
     */
    private function seedCases(
        BillingService $billing,
        MaternityService $maternity,
        array $resources,
        string $marker,
    ): void {
        foreach (self::CASES as $index => $case) {
            $caseNumber = $index + 1;
            [$firstName, $lastName] = self::MOTHER_NAMES[$index];
            $deliveryAt = $case['days_ago'] === null
                ? null
                : now()->subDays($case['days_ago'])->setTime(8 + ($index % 5), 15);
            $laborStartedAt = $deliveryAt?->copy()->subHours(6 + ($index % 4)) ?? now()->subHours(4);
            $firstAntenatalAt = $laborStartedAt->copy()->subDays(28 * $case['antenatal_visits']);
            $mother = Patient::create([
                'hospital_number' => sprintf('MAT-DEMO-%d-%02d', $this->hospitalId, $caseNumber),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => 'female',
                'date_of_birth' => now()->subYears(22 + ($index % 12))->toDateString(),
            ]);

            $registrationInvoice = $billing->registrationInvoiceFor($mother);
            if ($registrationInvoice) {
                $this->settleItems($registrationInvoice, $registrationInvoice->items()->get(), $resources['cashier'], "REG-{$caseNumber}", $firstAntenatalAt);
            } else {
                $mother->forceFill(['registration_paid_at' => $firstAntenatalAt])->saveQuietly();
            }

            $this->seedAntenatalHistory(
                $billing,
                $mother,
                $resources['antenatal_type'],
                $resources['antenatal_doctor'],
                $resources['cashier'],
                $laborStartedAt,
                $case,
                $marker,
                $caseNumber,
            );

            $admissionVisit = Visit::create([
                'patient_id' => $mother->id,
                'doctor_id' => $resources['attending_doctor']->id,
                'department_id' => $resources['maternity_ward']->id,
                'type' => 'consultation',
                'status' => VisitStatus::Inpatient,
                'complaint' => $case['label'].' (synthetic demo record).',
                'diagnosis' => 'Maternity admission for labor assessment (synthetic demo record).',
                'clinical_notes' => "{$marker} Case {$caseNumber}: {$case['label']}. Synthetic demonstration only.",
                'checked_in_at' => $laborStartedAt,
                'consulted_at' => $laborStartedAt->copy()->addMinutes(45),
                'created_at' => $laborStartedAt,
                'updated_at' => $laborStartedAt,
            ]);
            $admission = Admission::create([
                'admission_number' => sprintf('MAT-DEMO-%d-ADM-%02d', $this->hospitalId, $caseNumber),
                'visit_id' => $admissionVisit->id,
                'patient_id' => $mother->id,
                'department_id' => $resources['maternity_ward']->id,
                'attending_doctor_id' => $resources['attending_doctor']->id,
                'admitted_by' => $resources['attending_doctor']->id,
                'status' => AdmissionStatus::Active,
                'admitted_at' => $laborStartedAt,
                'expected_discharge_date' => $laborStartedAt->copy()->addDays(3)->toDateString(),
                'admission_reason' => $case['label'].' (synthetic demo record).',
                'admission_diagnosis' => 'Maternity admission for labor (synthetic demo record).',
                'initial_treatment_plan' => 'Continue observations and document care according to the responsible clinician and hospital protocol.',
                'created_at' => $laborStartedAt,
                'updated_at' => $laborStartedAt,
            ]);
            $episode = $maternity->startLabor($admission, [
                'labor_started_at' => $laborStartedAt->toDateTimeString(),
                'labor_assessment' => "{$marker} {$case['label']}; synthetic assessment for workflow testing only.",
                'patient_brought_delivery_materials' => $caseNumber % 2 === 0,
            ], $resources['attending_doctor']);
            $episode->forceFill(['created_at' => $laborStartedAt, 'updated_at' => $laborStartedAt])->saveQuietly();

            if (! $deliveryAt) {
                continue;
            }

            $episode = $maternity->recordDelivery($episode, [
                'delivery_at' => $deliveryAt->toDateTimeString(),
                'delivery_mode' => $case['mode'],
                'delivery_notes' => $case['label'].'; synthetic clinical record, not care guidance.',
                'hospital_supplied_delivery_pack' => $case['hospital_pack'],
                'delivery_material_notes' => $case['hospital_pack']
                    ? 'Hospital delivery pack issued for this synthetic case.'
                    : 'No hospital delivery pack issued for this synthetic case.',
                'newborns' => array_map(fn (array $baby): array => [
                    'outcome' => $baby['outcome'],
                    'first_name' => $baby['name'] ? Str::before($baby['name'], ' ') : null,
                    'last_name' => $baby['name'] ? Str::after($baby['name'], ' ') : null,
                    'gender' => $baby['gender'],
                    'birth_weight_grams' => $baby['weight'],
                    'apgar_score_1_minute' => $baby['apgar_1'],
                    'apgar_score_5_minutes' => $baby['apgar_5'],
                ], $case['babies']),
            ], $resources['attending_doctor']);
            $episode->update([
                'postpartum_notes' => 'Maternal and newborn follow-up documented for this synthetic workflow case.',
                'created_at' => $laborStartedAt,
                'updated_at' => $deliveryAt->copy()->addHours(2),
            ]);
            $this->timestampNewbornRecords($episode, $deliveryAt);

            $deliveryInvoice = Invoice::query()->where('visit_id', $admissionVisit->id)->first();
            if ($deliveryInvoice) {
                $items = $deliveryInvoice->items()->orderBy('id')->get();
                $itemsToSettle = match ($case['payment_profile']) {
                    'paid' => $items,
                    'mixed' => $items->take(1),
                    default => new Collection,
                };
                $this->settleItems($deliveryInvoice, $itemsToSettle, $resources['cashier'], "DEL-{$caseNumber}", $deliveryAt->copy()->addHours(3));
                $this->timestampInvoice($deliveryInvoice, $deliveryAt);
            }

            if (! $case['keep_active']) {
                $dischargedAt = $deliveryAt->copy()->addDays(2);
                $admission->update([
                    'status' => AdmissionStatus::Discharged,
                    'discharged_by' => $resources['attending_doctor']->id,
                    'discharged_at' => $dischargedAt,
                    'discharge_diagnosis' => 'Post-delivery discharge (synthetic demo record).',
                    'discharge_summary' => 'Mother and newborn workflow completed for this synthetic scenario.',
                    'discharge_medications' => 'See the synthetic medication record where applicable.',
                    'follow_up_instructions' => 'Follow the plan documented by the responsible maternity clinician.',
                    'patient_discharge_instructions' => 'Synthetic demo record; not patient-specific medical guidance.',
                    'updated_at' => $dischargedAt,
                ]);
                $admissionVisit->update([
                    'status' => VisitStatus::Completed,
                    'diagnosis' => 'Post-delivery inpatient care completed (synthetic demo record).',
                    'closed_at' => $dischargedAt,
                    'updated_at' => $dischargedAt,
                ]);
            }
        }
    }

    /** Create variable-length antenatal history with actual consultation invoices and receipts. */
    private function seedAntenatalHistory(
        BillingService $billing,
        Patient $mother,
        ConsultationType $antenatalType,
        User $doctor,
        User $cashier,
        Carbon $laborStartedAt,
        array $case,
        string $marker,
        int $caseNumber,
    ): void {
        for ($visitIndex = 0; $visitIndex < $case['antenatal_visits']; $visitIndex++) {
            $visitNumber = $visitIndex + 1;
            $daysBeforeLabor = 28 * ($case['antenatal_visits'] - $visitIndex);
            $checkedInAt = $laborStartedAt->copy()->subDays($daysBeforeLabor)->setTime(9 + ($visitIndex % 4), 0);
            $visit = Visit::create([
                'patient_id' => $mother->id,
                'doctor_id' => $doctor->id,
                'department_id' => $antenatalType->department_id,
                'consultation_type_id' => $antenatalType->id,
                'type' => 'consultation',
                'status' => VisitStatus::Completed,
                'complaint' => $visitNumber === 1 ? 'Antenatal booking assessment (synthetic demo record).' : 'Routine antenatal follow-up (synthetic demo record).',
                'diagnosis' => 'Antenatal consultation completed (synthetic demo record).',
                'clinical_notes' => "{$marker} Case {$caseNumber}, antenatal visit {$visitNumber} of {$case['antenatal_visits']}; synthetic example only.",
                'checked_in_at' => $checkedInAt,
                'consulted_at' => $checkedInAt->copy()->addMinutes(30),
                'closed_at' => $checkedInAt->copy()->addHour(),
                'created_at' => $checkedInAt,
                'updated_at' => $checkedInAt,
            ]);
            $invoice = $billing->invoiceFor($visit);
            $shouldSettle = match ($case['payment_profile']) {
                'paid' => true,
                'mixed' => $visitNumber % 2 === 1,
                default => false,
            };
            if ($shouldSettle) {
                $this->settleItems($invoice, $invoice->items()->get(), $cashier, "ANC-{$caseNumber}-{$visitNumber}", $checkedInAt->copy()->addMinutes(15));
            }
            $this->timestampInvoice($invoice, $checkedInAt);
        }
    }

    /** Record a receipt for whole selected line items, matching the cashier workflow's model. */
    private function settleItems(Invoice $invoice, Collection $items, User $cashier, string $reference, Carbon $paidAt): void
    {
        $items = $items->whereNull('payment_id')->where('total', '>', 0);
        $amount = (float) $items->sum('total');
        if ($amount <= 0 || $items->isEmpty()) {
            return;
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'pos'][$invoice->id % 3],
            'reference' => "MAT-DEMO-H{$this->hospitalId}-{$reference}",
            'received_by' => $cashier->id,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        $invoice->items()->whereIn('id', $items->modelKeys())->update(['payment_id' => $payment->id]);
        if ($items->contains('category', 'registration')) {
            $invoice->patient()->whereNull('registration_paid_at')->update(['registration_paid_at' => $paidAt]);
        }
        $invoice->recalculate();
    }

    /** Keep financial history aligned with the synthetic encounter date. */
    private function timestampInvoice(Invoice $invoice, Carbon $encounterAt): void
    {
        $invoice->forceFill(['created_at' => $encounterAt, 'updated_at' => $encounterAt])->saveQuietly();
        $invoice->items()->update(['created_at' => $encounterAt, 'updated_at' => $encounterAt]);
    }

    /** Keep birth and linked newborn-patient timestamps aligned with delivery. */
    private function timestampNewbornRecords(MaternityEpisode $episode, Carbon $deliveryAt): void
    {
        $episode->newborns()->with('patient')->get()->each(function ($newborn) use ($deliveryAt): void {
            $newborn->forceFill(['created_at' => $deliveryAt, 'updated_at' => $deliveryAt])->saveQuietly();
            $newborn->patient?->forceFill(['created_at' => $deliveryAt, 'updated_at' => $deliveryAt])->saveQuietly();
        });
    }
}
