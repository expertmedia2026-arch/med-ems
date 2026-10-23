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

class AdmissionCareChartService
{
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

    /** @param array<int, array{name:string,image:UploadedFile}> $witnesses */
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

    private function authorizeClinician(Admission $admission, User $actor): void
    {
        abort_unless($actor->hasRole(Role::Admin) || ($actor->hasRole(Role::Doctor)
            && ($admission->attending_doctor_id === $actor->id || (int) $actor->department_id === (int) $admission->department_id)), 403);
    }

    private function assertActive(Admission $admission): void
    {
        abort_unless($admission->status === AdmissionStatus::Active, 422, 'This chart can only be updated during an active admission.');
    }
}
