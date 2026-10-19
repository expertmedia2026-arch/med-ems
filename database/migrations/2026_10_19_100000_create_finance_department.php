<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $departmentId = DB::table('departments')->where('name', 'Accounting & Finance')->value('id');

        if (! $departmentId) {
            $departmentId = DB::table('departments')->insertGetId([
                'name' => 'Accounting & Finance',
                'description' => 'Payment collection, accounting, and financial oversight.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->where('role', 'cashier')->whereNull('department_id')
            ->update(['department_id' => $departmentId]);
    }

    public function down(): void
    {
        $departmentId = DB::table('departments')->where('name', 'Accounting & Finance')->value('id');

        if (! $departmentId) {
            return;
        }

        $hasStaff = DB::table('users')->where('department_id', $departmentId)->exists();
        $hasInvitations = DB::table('staff_invitations')->where('department_id', $departmentId)->exists();

        if (! $hasStaff && ! $hasInvitations) {
            DB::table('departments')->where('id', $departmentId)->delete();
        }
    }
};
