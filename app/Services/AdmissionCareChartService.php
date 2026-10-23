<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\AdmissionDamaRecord;
use App\Models\AdmissionFluidEntry;
use App\Models\AdmissionNebulizerUse;
use App\Models\AdmissionOxygenAdministration;
use App\Models\AdmissionSugarCheck;
use App\Models\HospitalSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AdmissionCareChartService
{
    public function recordSugar(Admission $admission, array $data, User $actor): AdmissionSugarCheck
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return $admission->sugarChecks()->create($data + ['checked_by' => $actor->id]);
    }

    public function recordFluid(Admission $admission, array $data, User $actor): AdmissionFluidEntry
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return $admission->fluidEntries()->create($data + ['recorded_by' => $actor->id]);
    }

    public function recordOxygen(Admission $admission, array $data, User $actor): AdmissionOxygenAdministration
    {
        $this->authorizeWardCare($admission, $actor);
        $this->assertActive($admission);

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $admin = $admission->oxygenAdministrations()->create($data + ['administered_by' => $actor->id]);

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
            $use = $admission->nebulizerUses()->create($data + [
                'recorded_by' => $actor->id,
                'usage_number' => $count,
                'billable_block' => $isChargeStart ? (int) ceil($count / 3) : null,
            ]);

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
