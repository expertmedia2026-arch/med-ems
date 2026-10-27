<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('hospitals')->pluck('id') as $hospitalId) {
            $legacy = DB::table('hospital_modules')
                ->where('hospital_id', $hospitalId)
                ->where('module', 'triage')
                ->first();
            $centralVitals = DB::table('hospital_modules')
                ->where('hospital_id', $hospitalId)
                ->where('module', 'central_vitals')
                ->first();

            if ($legacy && $centralVitals) {
                DB::table('hospital_modules')->where('id', $centralVitals->id)->update([
                    'enabled' => (bool) $legacy->enabled || (bool) $centralVitals->enabled,
                    'updated_at' => now(),
                ]);
                DB::table('hospital_modules')->where('id', $legacy->id)->delete();
            } elseif ($legacy) {
                DB::table('hospital_modules')->where('id', $legacy->id)->update([
                    'module' => 'central_vitals',
                    'updated_at' => now(),
                ]);
            } elseif (! $centralVitals) {
                DB::table('hospital_modules')->insert([
                    'hospital_id' => $hospitalId,
                    'module' => 'central_vitals',
                    'enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('hospital_modules')->where('module', 'central_vitals')->update(['module' => 'triage']);
    }
};
