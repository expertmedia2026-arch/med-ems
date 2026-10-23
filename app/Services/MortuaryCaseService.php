<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MortuaryCaseService
{
    public function declareInpatientDeath(Admission $admission, array $data, User $actor): MortuaryCase
    {
        abort_unless($actor->hasRole(Role::Admin, Role::Doctor), 403, 'Only an attending doctor or administrator can record an inpatient death.');
        abort_unless($actor->hasRole(Role::Admin)
            || $admission->attending_doctor_id === $actor->id
            || ($actor->department_id && (int) $actor->department_id === (int) $admission->department_id),
            403, 'Only the attending doctor or a doctor in this ward can record this inpatient death.');

        return DB::transaction(function () use ($admission, $data, $actor) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Active, 422, 'This admission is already closed.');
            $visit = Visit::query()->lockForUpdate()->findOrFail($admission->visit_id);
            abort_unless($visit->status === VisitStatus::Inpatient, 422, 'This record is not an active inpatient visit.');
            abort_if(MortuaryCase::query()->where('visit_id', $visit->id)->exists(), 422, 'A death or mortuary case is already recorded for this visit.');

            $actionTime = now();
            $directRelease = $data['disposition'] === 'direct_family_release';
            $settings = HospitalSetting::current();
            $case = MortuaryCase::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'source' => 'hospital',
                'status' => $directRelease ? 'released' : 'awaiting_transfer',
                'deceased_at' => $actionTime,
                'declared_at' => $actionTime,
                'declared_by' => $actor->id,
                'opened_by' => $actor->id,
                'cause_of_death' => $data['cause_of_death'],
                'death_clinical_summary' => $data['death_clinical_summary'],
                'release_route' => $data['disposition'],
                'released_by' => $directRelease ? $actor->id : null,
                'released_at' => $directRelease ? $actionTime : null,
                'released_to' => $directRelease ? $data['released_to'] : null,
                'recipient_relationship' => $directRelease ? $data['recipient_relationship'] : null,
                'recipient_id_reference' => $directRelease ? ($data['recipient_id_reference'] ?? null) : null,
                'release_authority' => $directRelease ? $data['release_authority'] : null,
                'release_note' => $directRelease ? ($data['release_note'] ?? null) : null,
                'intake_fee' => $directRelease ? null : (float) $settings->mortuary_intake_fee,
                'daily_storage_fee' => $directRelease ? null : (float) $settings->mortuary_daily_storage_fee,
                'state_tax_daily_fee' => $directRelease ? null : (float) $settings->state_mortuary_tax_daily_fee,
            ]);
            $case->update(['case_number' => $this->caseNumber($case)]);
            $invoice = app(BillingService::class)->invoiceFor($visit);
            $case->update(['invoice_id' => $invoice->id]);
            if (! $directRelease) {
                $this->addIntakeFee($case, $invoice);
            }

            $case->events()->create([
                'performed_by' => $actor->id,
                'event_type' => 'case_opened',
                'occurred_at' => $actionTime,
                'details' => [
                    'source' => 'inpatient',
                    'disposition' => $data['disposition'],
                    'cause_of_death' => $data['cause_of_death'],
                ],
            ]);

            if ($directRelease) {
                $case->events()->create([
                    'performed_by' => $actor->id,
                    'event_type' => 'released',
                    'occurred_at' => $actionTime,
                    'details' => array_filter([
                        'release_route' => 'direct_family_release',
                        'released_to' => $data['released_to'],
                        'relationship' => $data['recipient_relationship'],
                        'id_reference' => $data['recipient_id_reference'] ?? null,
                        'authority' => $data['release_authority'],
                        'note' => $data['release_note'] ?? null,
                    ]),
                ]);
            }

            $admission->update(['status' => AdmissionStatus::Deceased]);
            $visit->update([
                'status' => VisitStatus::Completed,
                'disposition' => 'deceased',
                'closed_at' => $actionTime,
                'discharged_by' => $actor->id,
                'discharged_at' => $actionTime,
            ]);
            $visit->appointment?->update(['status' => \App\Enums\AppointmentStatus::Completed]);

            return $case->refresh();
        });
    }

    public function openFromEmergency(Visit $visit, User $doctor, ?string $deceasedAt): MortuaryCase
    {
        $case = MortuaryCase::firstOrCreate(
            ['visit_id' => $visit->id],
            [
                'patient_id' => $visit->patient_id,
                'status' => 'awaiting_transfer',
                'source' => 'hospital',
                'deceased_at' => $deceasedAt ?? now(),
                'declared_at' => now(),
                'declared_by' => $doctor->id,
                'release_route' => 'mortuary_transfer',
                'cause_of_death' => $visit->diagnosis,
                'death_clinical_summary' => $visit->clinical_notes,
                'opened_by' => $doctor->id,
            ]
        );

        if (! $case->case_number) {
            $case->update(['case_number' => 'MOR-'.now()->format('Ymd').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);
        }

        $invoice = app(BillingService::class)->invoiceFor($visit);
        $case->update(['invoice_id' => $invoice->id]);
        $this->initializeMortuaryFees($case);
        $this->addIntakeFee($case, $invoice);

        if ($case->wasRecentlyCreated) {
            $case->events()->create([
                'performed_by' => $doctor->id,
                'event_type' => 'case_opened',
                'occurred_at' => now(),
                'details' => ['source' => 'emergency', 'deceased_at' => $case->deceased_at->toISOString()],
            ]);
        }

        return $case->refresh();
    }

    public function createWalkIn(array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($data, $staff) {
            $this->assertMortuaryStaff($staff);
            $deceasedAt = Carbon::parse($data['deceased_at']);
            $age = $data['estimated_age'] ?? null;
            $patient = Patient::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $age !== null ? $deceasedAt->copy()->subYears((int) $age)->toDateString() : $deceasedAt->toDateString(),
                'dob_estimated' => true,
                'identity_status' => 'external_mortuary',
                'phone' => $data['external_contact_phone'],
                'address' => $data['external_contact_address'] ?? null,
                'next_of_kin_name' => $data['external_contact_name'],
                'next_of_kin_phone' => $data['external_contact_phone'],
            ]);

            $case = MortuaryCase::create([
                'patient_id' => $patient->id,
                'source' => 'walk_in',
                'status' => 'awaiting_transfer',
                'deceased_at' => $deceasedAt,
                'deceased_age' => $age,
                'opened_by' => $staff->id,
                'handoff_note' => $data['handoff_note'] ?? null,
                'external_contact_name' => $data['external_contact_name'],
                'external_contact_phone' => $data['external_contact_phone'],
                'external_contact_address' => $data['external_contact_address'] ?? null,
                'intake_fee' => (float) HospitalSetting::current()->mortuary_intake_fee,
                'daily_storage_fee' => (float) HospitalSetting::current()->mortuary_daily_storage_fee,
                'state_tax_daily_fee' => (float) HospitalSetting::current()->state_mortuary_tax_daily_fee,
            ]);
            $case->update(['case_number' => $this->caseNumber($case)]);

            $invoice = Invoice::create(['patient_id' => $patient->id]);
            $case->update(['invoice_id' => $invoice->id]);
            $this->addIntakeFee($case, $invoice);

            $case->events()->create(['performed_by' => $staff->id, 'event_type' => 'case_opened', 'occurred_at' => now(), 'details' => ['source' => 'walk_in']]);

            return $case->refresh();
        });
    }

    public function list(array $filters = [])
    {
        $this->accrueStorageCharges();
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        return MortuaryCase::query()
            ->with(['patient', 'visit', 'openedBy', 'receivedBy', 'releasedBy', 'invoice'])
            ->when($status && in_array($status, ['awaiting_transfer', 'received', 'released'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('case_number', 'like', "%{$search}%")
                    ->orWhere('body_tag_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn (Builder $patient) => $patient
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('hospital_number', 'like', "%{$search}%"));
            }))
            ->orderByRaw("CASE status WHEN 'awaiting_transfer' THEN 0 WHEN 'received' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function details(MortuaryCase $case): MortuaryCase
    {
        if (in_array($case->status, ['awaiting_transfer', 'received'], true)) {
            DB::transaction(function () use ($case) {
                $locked = MortuaryCase::query()->lockForUpdate()->find($case->id);
                if ($locked && $locked->status === 'awaiting_transfer') {
                    $this->initializeMortuaryFees($locked);
                    $this->addIntakeFee($locked, $this->invoiceForCase($locked));
                } elseif ($locked && $locked->status === 'received') {
                    $this->accrueMortuaryFeesForLockedCase($locked, now(), true);
                }
            });
        }
        return $case->refresh()->load(['patient', 'visit.doctor', 'visit.admission.department', 'openedBy', 'declaredBy', 'receivedBy', 'releasedBy', 'events.actor', 'invoice.items.payment', 'invoice.payments.receiver']);
    }

    public function receive(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            abort_unless($staff->canReceiveEmergencyMortuaryHandoff(), 403);
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'awaiting_transfer', 422, 'This body has already been received by the mortuary.');
            $receivedAt = now();
            $this->initializeMortuaryFees($case);
            $invoice = $this->invoiceForCase($case);
            $this->addIntakeFee($case, $invoice);
            if (! $this->intakeFeeIsPaid($case, $invoice)) {
                return $case->refresh();
            }

            $case->update([
                'status' => 'received',
                'received_by' => $staff->id,
                'received_at' => $receivedAt,
                'storage_billed_through' => $receivedAt,
                'storage_location' => $data['storage_location'],
                'body_tag_number' => $data['body_tag_number'] ?? null,
                'handoff_note' => $data['handoff_note'] ?? null,
            ]);

            $case->events()->create([
                'performed_by' => $staff->id,
                'event_type' => 'received',
                'occurred_at' => $receivedAt,
                'details' => array_filter([
                    'storage_location' => $data['storage_location'],
                    'body_tag_number' => $data['body_tag_number'] ?? null,
                    'note' => $data['handoff_note'] ?? null,
                ]),
            ]);

            return $case->refresh();
        });
    }

    public function move(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            $this->assertMortuaryStaff($staff);
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'received', 422, 'Only a body currently in mortuary custody can be moved.');
            abort_unless($case->storage_location !== $data['storage_location'], 422, 'Enter a different storage location to record a move.');
            $previousLocation = $case->storage_location;
            $case->update(['storage_location' => $data['storage_location']]);
            $case->events()->create(['performed_by' => $staff->id, 'event_type' => 'location_changed', 'occurred_at' => now(), 'details' => array_filter(['from' => $previousLocation, 'to' => $data['storage_location'], 'note' => $data['note'] ?? null])]);

            return $case->refresh();
        });
    }

    public function release(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            $this->assertMortuaryStaff($staff);
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'received', 422, 'Only a body in mortuary custody can be released.');
            $releasedAt = now();
            $this->accrueMortuaryFeesForLockedCase($case, $releasedAt, true);
            $invoice = $this->invoiceForCase($case);
            if (! $this->mortuaryChargesArePaid($case, $invoice)) {
                return $case->refresh();
            }
            $case->update([
                'status' => 'released', 'released_by' => $staff->id, 'released_at' => $releasedAt,
                'release_route' => 'mortuary_release',
                'released_to' => $data['released_to'], 'recipient_relationship' => $data['recipient_relationship'],
                'recipient_id_reference' => $data['recipient_id_reference'] ?? null, 'release_authority' => $data['release_authority'],
                'release_note' => $data['release_note'] ?? null,
            ]);
            $case->events()->create(['performed_by' => $staff->id, 'event_type' => 'released', 'occurred_at' => $releasedAt, 'details' => array_filter([
                'released_to' => $data['released_to'], 'relationship' => $data['recipient_relationship'],
                'id_reference' => $data['recipient_id_reference'] ?? null, 'authority' => $data['release_authority'], 'note' => $data['release_note'] ?? null,
            ])]);

            return $case->refresh();
        });
    }

    public function accrueStorageCharges(): int
    {
        $count = 0;
        MortuaryCase::query()->where('status', 'received')->whereNotNull('storage_billed_through')->chunkById(100, function ($cases) use (&$count) {
            foreach ($cases as $candidate) {
                DB::transaction(function () use ($candidate, &$count) {
                    $case = MortuaryCase::query()->lockForUpdate()->find($candidate->id);
                    if ($case && $case->status === 'received' && $this->accrueMortuaryFeesForLockedCase($case, now())) {
                        $count++;
                    }
                });
            }
        });

        return $count;
    }

    private function accrueMortuaryFeesForLockedCase(MortuaryCase $case, Carbon $through, bool $includePartial = false): bool
    {
        if ($case->daily_storage_fee === null || $case->state_tax_daily_fee === null) {
            $this->initializeMortuaryFees($case);
        }
        $cursor = $case->storage_billed_through;
        $storageRate = (float) $case->daily_storage_fee;
        $taxRate = (float) $case->state_tax_daily_fee;
        if (! $cursor) {
            return false;
        }
        if ($through->lessThanOrEqualTo($cursor)) {
            return false;
        }

        $elapsedSeconds = (int) $cursor->diffInSeconds($through);
        $periods = intdiv($elapsedSeconds, 86400);
        $remainder = $elapsedSeconds % 86400;
        $partial = $includePartial && $remainder > 0;
        $quantity = $periods + ($partial ? 1 : 0);
        if ($quantity > 0) {
            $invoice = $this->invoiceForCase($case);
            if ($storageRate > 0) {
                $description = $partial
                    ? 'Mortuary storage through release (final partial 24-hour period included)'
                    : 'Mortuary storage fee · '.$quantity.' started 24-hour period(s)';
                $invoice->items()->create(['category' => 'mortuary', 'description' => $description, 'quantity' => $quantity, 'unit_price' => $storageRate, 'billable_type' => $case->getMorphClass(), 'billable_id' => $case->id]);
            }
            if ($taxRate > 0) {
                $description = 'State mortuary tax · '.$quantity.' started 24-hour period(s)';
                $invoice->items()->create(['category' => 'mortuary', 'description' => $description, 'quantity' => $quantity, 'unit_price' => $taxRate, 'billable_type' => $case->getMorphClass(), 'billable_id' => $case->id]);
            }
        }

        if ($includePartial) {
            $case->update(['storage_billed_through' => $cursor->copy()->addSeconds($quantity * 86400)]);
        } elseif ($periods > 0) {
            $case->update(['storage_billed_through' => $cursor->copy()->addSeconds($periods * 86400)]);
        }
        return $quantity > 0;
    }

    private function initializeMortuaryFees(MortuaryCase $case): void
    {
        $settings = HospitalSetting::current();
        $case->update([
            'intake_fee' => $case->intake_fee ?? (float) $settings->mortuary_intake_fee,
            'daily_storage_fee' => $case->daily_storage_fee ?? (float) $settings->mortuary_daily_storage_fee,
            'state_tax_daily_fee' => $case->state_tax_daily_fee ?? (float) $settings->state_mortuary_tax_daily_fee,
        ]);
        $case->refresh();
    }

    private function intakeFeeIsPaid(MortuaryCase $case, Invoice $invoice): bool
    {
        if ((float) $case->intake_fee <= 0) {
            return true;
        }

        $item = $invoice->items()->where('category', 'mortuary')->where('description', 'Mortuary intake fee')
            ->where('billable_type', $case->getMorphClass())->where('billable_id', $case->id)->first();

        return $item?->is_paid ?? false;
    }

    private function mortuaryChargesArePaid(MortuaryCase $case, Invoice $invoice): bool
    {
        return ! $invoice->items()->where('category', 'mortuary')
            ->where('billable_type', $case->getMorphClass())->where('billable_id', $case->id)
            ->get()->contains(fn (InvoiceItem $item) => ! $item->is_paid);
    }

    private function invoiceForCase(MortuaryCase $case): Invoice
    {
        if ($case->invoice_id) {
            return Invoice::findOrFail($case->invoice_id);
        }
        $invoice = $case->visit ? app(BillingService::class)->invoiceFor($case->visit) : Invoice::create(['patient_id' => $case->patient_id]);
        $case->update(['invoice_id' => $invoice->id]);
        return $invoice;
    }

    private function addIntakeFee(MortuaryCase $case, Invoice $invoice): void
    {
        $fee = (float) $case->intake_fee;
        $alreadyAdded = $invoice->items()->where('category', 'mortuary')->where('description', 'Mortuary intake fee')
            ->where('billable_type', $case->getMorphClass())->where('billable_id', $case->id)->exists();
        if ($fee > 0 && ! $alreadyAdded) {
            $invoice->items()->create(['category' => 'mortuary', 'description' => 'Mortuary intake fee', 'quantity' => 1, 'unit_price' => $fee, 'billable_type' => $case->getMorphClass(), 'billable_id' => $case->id]);
        }
    }

    private function caseNumber(MortuaryCase $case): string
    {
        return 'MOR-'.now()->format('Ymd').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT);
    }

    private function assertMortuaryStaff(User $staff): void
    {
        abort_unless($staff->canAccessMortuary(), 403, 'Only mortuary attendants or administrators can update a mortuary case.');
    }
}
