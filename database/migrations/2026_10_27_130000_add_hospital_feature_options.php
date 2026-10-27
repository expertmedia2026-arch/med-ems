<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = [
        'patient_portal',
        'triage',
        'medication_administration',
        'emergency_pharmacy',
        'lab_inventory',
        'online_payments',
        'finance_reports',
    ];

    public function up(): void
    {
        $now = now();
        foreach (DB::table('hospitals')->pluck('id') as $hospitalId) {
            $currentlyEnabled = DB::table('hospital_modules')
                ->where('hospital_id', $hospitalId)
                ->where('enabled', true)
                ->pluck('module')
                ->all();

            foreach (self::MODULES as $module) {
                $enabled = match ($module) {
                    'emergency_pharmacy' => in_array('emergency', $currentlyEnabled, true) && in_array('pharmacy', $currentlyEnabled, true),
                    'lab_inventory' => in_array('laboratory', $currentlyEnabled, true),
                    default => true,
                };

                DB::table('hospital_modules')->updateOrInsert(
                    ['hospital_id' => $hospitalId, 'module' => $module],
                    ['enabled' => $enabled, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('hospital_modules')->whereIn('module', self::MODULES)->delete();
    }
};
