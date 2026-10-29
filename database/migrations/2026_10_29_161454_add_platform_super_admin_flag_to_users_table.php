<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an explicit top-level platform permission while preserving existing global admins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_super_admin')->default(false);
        });

        DB::table('users')
            ->where('is_global_admin', true)
            ->whereNull('hospital_id')
            ->update(['is_platform_super_admin' => true]);
    }

    /**
     * Remove the platform super-admin distinction.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_super_admin');
        });
    }
};
