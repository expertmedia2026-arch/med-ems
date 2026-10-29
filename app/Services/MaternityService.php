<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Models\Admission;
use App\Models\HospitalSetting;
use App\Models\MaternityEpisode;
use App\Models\MaternityNewborn;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaternityService
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * @return array{
     *     vaginal: float,
     *     assisted_vaginal: float,
     *     cesarean: float,
     *     other: float,
     *     delivery_pack: float
     * }
     */
    public function deliveryPricing(): array
    {
        $settings = HospitalSetting::current();

        return [
            'vaginal' => (float) $settings->maternity_vaginal_delivery_fee,
            'assisted_vaginal' => (float) $settings->maternity_assisted_delivery_fee,
            'cesarean' => (float) $settings->maternity_cesarean_delivery_fee,
            'other' => (float) $settings->maternity_other_delivery_fee,
            'delivery_pack' => (float) $settings->maternity_delivery_pack_fee,
        ];
    }

    public function indexData(User $viewer): LengthAwarePaginator
    {
        $query = MaternityEpisode::query()->with(['admission.patient', 'newborns.patient']);

        if ($viewer->hasRole(Role::Nurse)) {
            $query->whereHas('admission', fn ($admissions) => $admissions->where('department_id', $viewer->department_id));
        } elseif ($viewer->hasRole(Role::Doctor)) {
            $query->whereHas('admission', fn ($admissions) => $admissions
                ->where('attending_doctor_id', $viewer->id)
                ->when($viewer->department_id, fn ($admissions) => $admissions->orWhere('department_id', $viewer->department_id)));
        } else {
            abort_unless($viewer->hasRole(Role::Admin), 403);
        }

        return $query->latest('id')->paginate(20);
    }

    public function startLabor(Admission $admission, array $data, User $actor): MaternityEpisode
    {
        $this->assertAdmissionAccess($admission, $actor);
        abort_unless($admission->status === AdmissionStatus::Active, 422, 'A maternity episode can only be started for an active admission.');

        return DB::transaction(function () use ($admission, $data, $actor): MaternityEpisode {
            $lockedAdmission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($lockedAdmission->status === AdmissionStatus::Active, 422, 'A maternity episode can only be started for an active admission.');
            abort_if($lockedAdmission->maternityEpisode()->exists(), 422, 'A maternity episode already exists for this admission.');

            return MaternityEpisode::create([
                'admission_id' => $lockedAdmission->id,
                'created_by' => $actor->id,
                'status' => 'in_labor',
                'labor_started_at' => isset($data['labor_started_at'])
                    ? Carbon::parse($data['labor_started_at'])
                    : now(),
                'labor_assessment' => $data['labor_assessment'] ?? null,
                'patient_brought_delivery_materials' => $data['patient_brought_delivery_materials'],
            ]);
        });
    }

    public function show(MaternityEpisode $episode, User $viewer): MaternityEpisode
    {
        $this->assertAdmissionAccess($episode->admission, $viewer);

        return $episode->load(['admission.patient', 'admission.department', 'newborns.patient', 'createdBy']);
    }

    public function recordDelivery(MaternityEpisode $episode, array $data, User $actor): MaternityEpisode
    {
        $this->assertAdmissionAccess($episode->admission, $actor);

        return DB::transaction(function () use ($episode, $data): MaternityEpisode {
            $episode = MaternityEpisode::query()
                ->with('admission.visit')
                ->lockForUpdate()
                ->findOrFail($episode->id);
            abort_unless($episode->status === 'in_labor', 422, 'Delivery details can only be recorded for an episode in labor.');
            abort_unless($episode->admission->status === AdmissionStatus::Active, 422, 'The linked admission is no longer active.');

            $deliveryAt = Carbon::parse($data['delivery_at']);
            if ($deliveryAt->lt($episode->labor_started_at)) {
                throw ValidationException::withMessages([
                    'delivery_at' => 'Delivery time cannot be before labor started.',
                ]);
            }

            foreach ($data['newborns'] as $index => $newbornData) {
                $patient = null;
                if ($newbornData['outcome'] === 'live_birth') {
                    $patient = Patient::create([
                        'first_name' => $newbornData['first_name'],
                        'last_name' => $newbornData['last_name'],
                        'gender' => $newbornData['gender'],
                        'date_of_birth' => $deliveryAt->toDateString(),
                    ]);
                }

                MaternityNewborn::create([
                    'maternity_episode_id' => $episode->id,
                    'patient_id' => $patient?->id,
                    'birth_order' => $index + 1,
                    'outcome' => $newbornData['outcome'],
                    'first_name' => $newbornData['first_name'] ?? null,
                    'last_name' => $newbornData['last_name'] ?? null,
                    'gender' => $newbornData['gender'] ?? null,
                    'date_of_birth' => $deliveryAt->toDateString(),
                ]);
            }

            $episode->update([
                'status' => 'postpartum',
                'delivery_at' => $deliveryAt,
                'delivery_mode' => $data['delivery_mode'],
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'hospital_supplied_delivery_pack' => $data['hospital_supplied_delivery_pack'],
                'delivery_material_notes' => $data['delivery_material_notes'] ?? null,
            ]);

            $pricing = $this->deliveryPricing();
            $deliveryFee = $pricing[$data['delivery_mode']];
            $packFee = $data['hospital_supplied_delivery_pack'] ? $pricing['delivery_pack'] : 0;

            if ($deliveryFee > 0 || $packFee > 0) {
                $invoice = $this->billing->invoiceFor($episode->admission->visit);

                if ($deliveryFee > 0) {
                    $invoice->items()->create([
                        'category' => 'maternity',
                        'description' => str($data['delivery_mode'])->replace('_', ' ')->title().' delivery fee',
                        'quantity' => 1,
                        'unit_price' => $deliveryFee,
                        'billable_type' => $episode->getMorphClass(),
                        'billable_id' => $episode->id,
                    ]);
                }

                if ($packFee > 0) {
                    $invoice->items()->create([
                        'category' => 'maternity',
                        'description' => 'Hospital-supplied delivery pack',
                        'quantity' => 1,
                        'unit_price' => $packFee,
                        'billable_type' => $episode->getMorphClass(),
                        'billable_id' => $episode->id,
                    ]);
                }
            }

            return $episode->refresh()->load(['admission.patient', 'newborns.patient']);
        });
    }

    public function updatePostpartum(MaternityEpisode $episode, string $notes, User $actor): MaternityEpisode
    {
        $this->assertAdmissionAccess($episode->admission, $actor);
        abort_unless($episode->status === 'postpartum', 422, 'Postpartum notes can only be recorded after delivery.');
        abort_unless($episode->admission->status === AdmissionStatus::Active, 422, 'The linked admission is no longer active.');

        $episode->update(['postpartum_notes' => $notes]);

        return $episode->refresh()->load(['admission.patient', 'newborns.patient']);
    }

    private function assertAdmissionAccess(Admission $admission, User $viewer): void
    {
        if ($viewer->hasRole(Role::Admin)) {
            return;
        }

        if ($viewer->hasRole(Role::Doctor)
            && ($admission->attending_doctor_id === $viewer->id
                || ($viewer->department_id && (int) $viewer->department_id === (int) $admission->department_id))) {
            return;
        }

        abort_unless($viewer->hasRole(Role::Nurse)
            && (int) $viewer->department_id === (int) $admission->department_id,
            403, 'Only the attending doctor, a doctor in this ward department, or a nurse assigned to this ward can access the maternity episode.');
    }
}
