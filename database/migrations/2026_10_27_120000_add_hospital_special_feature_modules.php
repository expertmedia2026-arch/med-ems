<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = ['external_lab', 'staff_roster', 'appointments', 'patient_id_card'];

    public function up(): void
    {
        $now = now();
        $hospitalIds = DB::table('hospitals')->pluck('id');

        foreach ($hospitalIds as $hospitalId) {
            foreach (self::MODULES as $module) {
                DB::table('hospital_modules')->updateOrInsert(
                    ['hospital_id' => $hospitalId, 'module' => $module],
                    ['enabled' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('hospital_modules')->whereIn('module', self::MODULES)->delete();
    }
};
