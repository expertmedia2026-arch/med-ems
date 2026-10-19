<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $departmentId = DB::table('departments')->where('name', 'Pharmacy')->value('id');

        if (! $departmentId) {
            $departmentId = DB::table('departments')->insertGetId([
                'name' => 'Pharmacy',
                'description' => 'Medication dispensing, inventory, and pharmacy oversight.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->whereIn('role', ['pharmacist', 'chief_pharmacist'])
            ->whereNull('department_id')
            ->update(['department_id' => $departmentId]);

        DB::table('staff_invitations')->whereIn('role', ['pharmacist', 'chief_pharmacist'])
            ->whereNull('department_id')
            ->update(['department_id' => $departmentId]);
    }

    public function down(): void
    {
        $departmentId = DB::table('departments')->where('name', 'Pharmacy')->value('id');

        if (! $departmentId) {
            return;
        }

        DB::table('users')->where('department_id', $departmentId)
            ->whereIn('role', ['pharmacist', 'chief_pharmacist'])
            ->update(['department_id' => null]);
        DB::table('staff_invitations')->where('department_id', $departmentId)
            ->whereIn('role', ['pharmacist', 'chief_pharmacist'])
            ->update(['department_id' => null]);

        if (! DB::table('users')->where('department_id', $departmentId)->exists()
            && ! DB::table('staff_invitations')->where('department_id', $departmentId)->exists()) {
            DB::table('departments')->where('id', $departmentId)->delete();
        }
    }
};
