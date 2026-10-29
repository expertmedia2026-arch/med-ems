<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table): void {
            $table->decimal('maternity_vaginal_delivery_fee', 10, 2)->default(0);
            $table->decimal('maternity_assisted_delivery_fee', 10, 2)->default(0);
            $table->decimal('maternity_cesarean_delivery_fee', 10, 2)->default(0);
            $table->decimal('maternity_other_delivery_fee', 10, 2)->default(0);
            $table->decimal('maternity_delivery_pack_fee', 10, 2)->default(0);
        });

        Schema::table('maternity_episodes', function (Blueprint $table): void {
            $table->boolean('patient_brought_delivery_materials')->nullable();
            $table->boolean('hospital_supplied_delivery_pack')->nullable();
            $table->text('delivery_material_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maternity_episodes', function (Blueprint $table): void {
            $table->dropColumn([
                'patient_brought_delivery_materials',
                'hospital_supplied_delivery_pack',
                'delivery_material_notes',
            ]);
        });

        Schema::table('hospital_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'maternity_vaginal_delivery_fee',
                'maternity_assisted_delivery_fee',
                'maternity_cesarean_delivery_fee',
                'maternity_other_delivery_fee',
                'maternity_delivery_pack_fee',
            ]);
        });
    }
};
