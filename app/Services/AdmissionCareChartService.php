<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Admission;
use App\Models\AdmissionDamaRecord;
use App\Models\AdmissionFluidEntry;
use App\Models\AdmissionInsulinMachineBlock;
use App\Models\AdmissionInsulinMachineUse;
use App\Models\AdmissionNebulizerUse;
use App\Models\AdmissionOxygenAdministration;
use App\Models\AdmissionSugarCheck;
use App\Models\HospitalSetting;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Records inpatient observations, fluid/equipment use, and discharge-against-advice records.
 */
class AdmissionCareChartService
{
    /**
     * Save a blood-sugar reading and add its configured care charge to the visit invoice when applicable.
     *
     * Administrators, ward nurses, and authorized ward doctors may use this on an active admission;
     * the admission lock keeps the chart entry and any invoice line in the same transaction.
     *
     * @param array<string, mixed> $data Validated reading, units, and reading type.
     */
    public function recordSugar(Admission $admission, array $data, User $actor): AdmissionSugarCheck
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $check = $admission->sugarChecks()->create(array_merge($data, [
                'checked_by' => $actor->id,
                'checked_at' => now(),
            ]));
            $fee = (float) HospitalSetting::current()->sugar_check_fee;

            if ($fee > 0) {
                $invoice = app(BillingService::class)->invoiceFor($admission->visit);
                $invoice->items()->create([
                    'category' => 'care',
                    'description' => $data['reading_type'].' blood sugar check',
                    'quantity' => 1,
                    'unit_price' => $fee,
                    'billable_type' => $check->getMorphClass(),
                    'billable_id' => $check->id,
                ]);
            }

            return $check;
        });
    }

    /**
     * Record fluid intake/output, requiring intake to reference a paid, dispensed fluid prescription.
     *
     * Only authorized staff may add entries to an active admission. The chart entry is written
     * transactionally with its admission lock; this method does not create a fluid charge.
     *
     * @param array<string, mixed> $data Validated flow, amount, route/output, and optional prescription ID.
     */
    public function recordFluid(Admission $admission, array $data, User $actor): AdmissionFluidEntry
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $prescription = null;

            if ($data['flow'] === 'intake') {
                $prescription = Prescription::query()->with(['drug', 'invoiceItem', 'stockReservation'])
                    ->lockForUpdate()->findOrFail($data['prescription_id']);
                abort_unless($prescription->visit_id === $admission->visit_id, 422, 'Choose a fluid prescription linked to this admission.');
                abort_unless($prescription->drug->is_fluid, 422, 'Choose a product marked as a fluid in the pharmacy drug catalogue.');
                abort_unless($prescription->is_paid, 422, 'Pay the fluid prescription before recording intake.');
                abort_unless($prescription->status === WorkStatus::Completed && $prescription->stockReservation?->status === 'fulfilled', 422, 'Pharmacy must dispense this fluid before intake can be recorded.');
            }

            return $admission->fluidEntries()->create([
                'prescription_id' => $prescription?->id,
                'recorded_by' => $actor->id,
                'flow' => $data['flow'],
                'route_or_output' => $data['route_or_output'],
                'fluid_type' => $prescription?->drug->display_name,
                'quantity_ml' => $data['quantity_ml'],
                'drops_per_minute' => $data['drops_per_minute'] ?? null,
                'recorded_at' => now(),
            ]);
        });
    }

    /**
     * Record an oxygen administration and add its configured per-administration invoice item.
     *
     * The active admission row, administration, and any charge are handled in one transaction;
     * role and ward-scope checks run before recording.
     *
     * @param array<string, mixed> $data Validated oxygen flow/concentration and administration details.
     */
    public function recordOxygen(Admission $admission, array $data, User $actor): AdmissionOxygenAdministration
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $admin = $admission->oxygenAdministrations()->create(array_merge($data, [
                'administered_by' => $actor->id,
                'administered_at' => now(),
            ]));

            $fee = (float) HospitalSetting::current()->oxygen_per_administration_fee;
            if ($fee > 0) {
                $invoice = app(BillingService::class)->invoiceFor($admission->visit);
                if (! $invoice->items()->where('billable_type', $admin->getMorphClass())->where('billable_id', $admin->id)->exists()) {
                    $invoice->items()->create([
                        'category' => 'oxygen', 'description' => 'Oxygen therapy administration',
                        'quantity' => 1, 'unit_price' => $fee,
                        'billable_type' => $admin->getMorphClass(), 'billable_id' => $admin->id,
                    ]);
                }
            }

            return $admin->refresh();
        });
    }

    /**
     * Record nebulizer use and charge only the first use in each three-use block.
     *
     * The use number and invoice line are created under the active admission's transaction lock,
     * preventing concurrent chart entries from sharing a block number.
     *
     * @param array<string, mixed> $data Validated treatment details for the nebulizer session.
     */
    public function recordNebulizerUse(Admission $admission, array $data, User $actor): AdmissionNebulizerUse
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $count = $admission->nebulizerUses()->count() + 1;
            $isChargeStart = ($count - 1) % 3 === 0;
            $use = $admission->nebulizerUses()->create(array_merge($data, [
                'recorded_by' => $actor->id,
                'used_at' => now(),
                'usage_number' => $count,
                'billable_block' => $isChargeStart ? (int) ceil($count / 3) : null,
            ]));

            $fee = (float) HospitalSetting::current()->nebulizer_fee_per_three_uses;
            if ($isChargeStart && $fee > 0) {
                $invoice = app(BillingService::class)->invoiceFor($admission->visit);
                $invoice->items()->create([
                    'category' => 'equipment', 'description' => 'Nebulizer machine use (up to 3 uses)',
                    'quantity' => 1, 'unit_price' => $fee,
                    'billable_type' => $use->getMorphClass(), 'billable_id' => $use->id,
                ]);
            }

            return $use;
        });
    }

    /**
     * Start or reuse an insulin-machine block, requiring an insulin prescription on the visit.
     *
     * A block permits up to three bottle uses. Creation and its optional configured equipment
     * invoice line are transactional; the latest block is returned while it is unpaid or has fewer
     * than three uses.
     */
    public function startInsulinMachineBlock(Admission $admission, User $actor): AdmissionInsulinMachineBlock
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $hasInsulinPrescription = $admission->visit->prescriptions()
                ->whereHas('drug', fn ($drug) => $drug
                    ->whereRaw('LOWER(name) LIKE ?', ['%insulin%'])
                    ->orWhereRaw('LOWER(generic_name) LIKE ?', ['%insulin%']))
                ->exists();
            abort_unless($hasInsulinPrescription, 422, 'An insulin prescription is required before activating the insulin machine.');

            $latest = $admission->insulinMachineBlocks()->with('invoiceItem')->latest('block_number')->first();

            if ($latest && (! $latest->payment_complete || $latest->uses()->count() < 3)) {
                return $latest;
            }

            $fee = (float) HospitalSetting::current()->nebulizer_fee_per_three_uses;
            $block = $admission->insulinMachineBlocks()->create([
                'started_by' => $actor->id,
                'block_number' => ($latest?->block_number ?? 0) + 1,
                'fee' => $fee,
                'started_at' => now(),
            ]);

            if ($fee > 0) {
                $invoice = app(BillingService::class)->invoiceFor($admission->visit);
                $invoice->items()->create([
                    'category' => 'equipment',
                    'description' => 'Insulin machine use block #'.$block->block_number.' (up to 3 bottles)',
                    'quantity' => 1,
                    'unit_price' => $fee,
                    'billable_type' => $block->getMorphClass(),
                    'billable_id' => $block->id,
                ]);
            }

            return $block->load('invoiceItem.payment');
        });
    }

    /**
     * Record opening a dispensed insulin bottle against the current paid block.
     *
     * The prescription must belong to this admission, be paid and fulfilled by pharmacy, and the
     * block must have capacity. The entry and block checks are protected by a transaction and locks.
     *
     * @param array<string, mixed> $data Validated prescription ID and optional bottle label.
     */
    public function recordInsulinMachineUse(Admission $admission, array $data, User $actor): AdmissionInsulinMachineUse
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $block = $admission->insulinMachineBlocks()->with('invoiceItem')->lockForUpdate()->latest('block_number')->first();
            abort_unless($block, 422, 'Start an insulin machine block before recording a bottle use.');
            abort_unless($block->payment_complete, 422, 'Pay the insulin machine block invoice before recording insulin use.');
            abort_unless($block->uses()->count() < 3, 422, 'This three-bottle machine block is complete. Start the next block to continue.');

            $prescription = Prescription::query()->with(['drug', 'invoiceItem', 'stockReservation'])->lockForUpdate()->findOrFail($data['prescription_id']);
            abort_unless($prescription->visit_id === $admission->visit_id, 422, 'Choose an insulin prescription linked to this admission.');
            abort_unless(str_contains(strtolower($prescription->drug->display_name), 'insulin'), 422, 'Choose an insulin prescription.');
            abort_unless($prescription->is_paid, 422, 'Pay the insulin prescription before opening a bottle.');
            abort_unless($prescription->status === \App\Enums\WorkStatus::Completed && $prescription->stockReservation?->status === 'fulfilled', 422, 'Pharmacy must dispense this insulin before a bottle can be opened.');

            return $block->uses()->create([
                'admission_id' => $admission->id,
                'prescription_id' => $prescription->id,
                'opened_by' => $actor->id,
                'bottle_label' => $data['bottle_label'] ?? null,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Complete a discharge against medical advice and save any uploaded witness records.
     *
     * Only an authorized clinician may complete this for an active admission. The DAMA record,
     * witness metadata, admission discharge, and linked visit closure are committed together.
     *
     * @param array<string, mixed> $data Validated representative, reason, explained risks, and follow-up details.
     * @param array<int, array{name:string,image:UploadedFile}> $witnesses Validated witness names and image uploads.
     */
    public function dischargeDama(Admission $admission, array $data, array $witnesses, User $actor): AdmissionDamaRecord
    {
        $this->authorizeClinician($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $witnesses, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Active, 422, 'This admission has already been discharged.');
            $record = $admission->damaRecord()->create([
                'completed_by' => $actor->id,
                'patient_or_representative' => $data['patient_or_representative'],
                'relationship' => $data['relationship'] ?? null,
                'reason' => $data['reason'],
                'risks_explained' => $data['risks_explained'],
                'follow_up_advice' => $data['follow_up_advice'],
                'additional_notes' => $data['additional_notes'] ?? null,
                'completed_at' => now(),
            ]);
            foreach ($witnesses as $witness) {
                $path = $witness['image']->store('admission-dama-witnesses', 'local');
                $record->witnesses()->create(['name' => $witness['name'], 'image_path' => $path]);
            }

            $admission->update([
                'status' => AdmissionStatus::Discharged,
                'discharged_by' => $actor->id,
                'discharged_at' => now(),
                'discharge_diagnosis' => $admission->admission_diagnosis,
                'discharge_summary' => 'Discharged against medical advice. See the signed DAMA record.',
                'follow_up_instructions' => 'See the saved discharge against medical advice record.',
            ]);
            $admission->visit()->update([
                'status' => VisitStatus::Completed,
                'closed_at' => now(),
                'discharged_by' => $actor->id,
                'discharged_at' => now(),
            ]);

            return $record->load('witnesses', 'clinician');
        });
    }

    /** Enforce role and ward-department access for routine inpatient care-chart entries. */
    private function authorizeWardCare(Admission $admission, User $actor): void
    {
        abort_unless($actor->hasRole(Role::Admin, Role::Nurse, Role::Doctor), 403);
        if ($actor->hasRole(Role::Admin)) {
            return;
        }
        if ($actor->hasRole(Role::Nurse)) {
            abort_unless((int) $actor->department_id === (int) $admission->department_id, 403);
        }
        if ($actor->hasRole(Role::Doctor)) {
            abort_unless($admission->attending_doctor_id === $actor->id || (int) $actor->department_id === (int) $admission->department_id, 403);
        }
    }

    /** Restrict DAMA discharge to administrators or doctors assigned to/serving the admission's ward. */
    private function authorizeClinician(Admission $admission, User $actor): void
    {
        abort_unless($actor->hasRole(Role::Admin) || ($actor->hasRole(Role::Doctor)
            && ($admission->attending_doctor_id === $actor->id || (int) $actor->department_id === (int) $admission->department_id)), 403);
    }

    /** Reject chart updates after the admission has left active status. */
    private function assertActive(Admission $admission): void
    {
        abort_unless($admission->status === AdmissionStatus::Active, 422, 'This chart can only be updated during an active admission.');
    }
}
