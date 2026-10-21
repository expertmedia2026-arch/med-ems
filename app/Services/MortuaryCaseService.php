<?php

namespace App\Services;

use App\Models\MortuaryCase;
use App\Models\User;
use App\Models\Visit;
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

        return $case->refresh();
    }

    public function receive(MortuaryCase $case, array $data, User $staff): MortuaryCase
    {
        return DB::transaction(function () use ($case, $data, $staff) {
            $case = MortuaryCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->status === 'awaiting_transfer', 422, 'This body has already been received by the mortuary.');

            $case->update([
                'status' => 'received',
                'received_by' => $staff->id,
                'received_at' => now(),
                'storage_location' => $data['storage_location'],
                'handoff_note' => $data['handoff_note'] ?? null,
            ]);

            return $case->refresh();
        });
    }
}
