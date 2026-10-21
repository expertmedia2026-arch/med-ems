<?php

namespace App\Services;

use App\Models\MortuaryCase;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MortuaryCaseService
{
    public function openFromEmergency(Visit $visit, User $doctor, ?string $deceasedAt): MortuaryCase
    {
        $case = MortuaryCase::firstOrCreate(
            ['visit_id' => $visit->id],
            [
                'patient_id' => $visit->patient_id,
                'status' => 'awaiting_transfer',
                'deceased_at' => $deceasedAt ?? now(),
                'opened_by' => $doctor->id,
            ]
        );

        if (! $case->case_number) {
            $case->update(['case_number' => 'MOR-'.now()->format('Ymd').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);
        }

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

    public function list(array $filters = [])
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        return MortuaryCase::query()
            ->with(['patient', 'visit', 'openedBy', 'receivedBy', 'releasedBy'])
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
        return $case->load(['patient', 'visit.doctor', 'openedBy', 'receivedBy', 'releasedBy', 'events.actor']);
    }

    public function receive(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            abort_unless($staff->canReceiveEmergencyMortuaryHandoff(), 403);
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'awaiting_transfer', 422, 'This body has already been received by the mortuary.');

            $case->update([
                'status' => 'received',
                'received_by' => $staff->id,
                'received_at' => now(),
                'storage_location' => $data['storage_location'],
                'body_tag_number' => $data['body_tag_number'] ?? null,
                'handoff_note' => $data['handoff_note'] ?? null,
            ]);

            $case->events()->create([
                'performed_by' => $staff->id,
                'event_type' => 'received',
                'occurred_at' => now(),
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
            $case->events()->create([
                'performed_by' => $staff->id,
                'event_type' => 'location_changed',
                'occurred_at' => now(),
                'details' => array_filter([
                    'from' => $previousLocation,
                    'to' => $data['storage_location'],
                    'note' => $data['note'] ?? null,
                ]),
            ]);

            return $case->refresh();
        });
    }

    public function release(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            $this->assertMortuaryStaff($staff);
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'received', 422, 'Only a body in mortuary custody can be released.');

            $case->update([
                'status' => 'released',
                'released_by' => $staff->id,
                'released_at' => now(),
                'released_to' => $data['released_to'],
                'recipient_relationship' => $data['recipient_relationship'],
                'recipient_id_reference' => $data['recipient_id_reference'] ?? null,
                'release_authority' => $data['release_authority'],
                'release_note' => $data['release_note'] ?? null,
            ]);
            $case->events()->create([
                'performed_by' => $staff->id,
                'event_type' => 'released',
                'occurred_at' => now(),
                'details' => array_filter([
                    'released_to' => $data['released_to'],
                    'relationship' => $data['recipient_relationship'],
                    'id_reference' => $data['recipient_id_reference'] ?? null,
                    'authority' => $data['release_authority'],
                    'note' => $data['release_note'] ?? null,
                ]),
            ]);

            return $case->refresh();
        });
    }

    private function assertMortuaryStaff(User $staff): void
    {
        abort_unless($staff->canAccessMortuary(), 403, 'Only mortuary attendants or administrators can update a mortuary case.');
    }
}
