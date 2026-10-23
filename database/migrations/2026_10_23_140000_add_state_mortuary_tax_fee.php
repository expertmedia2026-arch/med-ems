<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->decimal('state_mortuary_tax_daily_fee', 10, 2)->default(0);
        });

        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->decimal('state_tax_daily_fee', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropColumn('state_tax_daily_fee');
        });

        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn('state_mortuary_tax_daily_fee');
        });
    }
};
