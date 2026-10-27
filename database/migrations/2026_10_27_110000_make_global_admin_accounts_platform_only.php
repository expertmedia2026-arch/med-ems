<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing tenant admins remain hospital admins; platform admin accounts are separate.
        DB::table('users')
            ->where('is_global_admin', true)
            ->whereNotNull('hospital_id')
            ->update(['is_global_admin' => false]);
    }

    public function down(): void
    {
        // Global-admin ownership is intentionally not reassigned automatically on rollback.
    }
};
