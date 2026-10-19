<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $financeDepartmentId = DB::table('departments')
            ->where('name', 'Accounting & Finance')
            ->value('id');

        DB::table('users')->where('role', 'cashier')->update([
            'role' => 'accountant',
            'department_id' => $financeDepartmentId,
        ]);

        DB::table('staff_invitations')->where('role', 'cashier')->update([
            'role' => 'accountant',
            'department_id' => $financeDepartmentId,
        ]);
    }

    public function down(): void
    {
        // Cashier accounts were merged into the Accountant role; keep that assignment on rollback.
    }
};
