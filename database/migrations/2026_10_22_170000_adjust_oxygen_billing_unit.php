<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->renameColumn('oxygen_daily_fee', 'oxygen_per_administration_fee');
        });

        Schema::table('admission_oxygen_administrations', function (Blueprint $table) {
            $table->dropColumn('billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->renameColumn('oxygen_per_administration_fee', 'oxygen_daily_fee');
        });

        Schema::table('admission_oxygen_administrations', function (Blueprint $table) {
            $table->date('billing_date')->nullable();
            $table->index(['admission_id', 'billing_date']);
        });
    }
};
