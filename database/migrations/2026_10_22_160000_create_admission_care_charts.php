<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->decimal('oxygen_daily_fee', 10, 2)->default(0);
            $table->decimal('nebulizer_fee_per_three_uses', 10, 2)->default(0);
        });

        Schema::create('admission_sugar_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->string('reading_type', 3);
            $table->decimal('result', 6, 2);
            $table->string('unit', 10);
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['admission_id', 'checked_at']);
        });

        Schema::create('admission_fluid_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('flow', 10);
            $table->string('route_or_output', 30);
            $table->string('fluid_type')->nullable();
            $table->unsignedInteger('quantity_ml');
            $table->unsignedSmallInteger('drops_per_minute')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['admission_id', 'recorded_at']);
        });

        Schema::create('admission_oxygen_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administered_by')->constrained('users')->restrictOnDelete();
            $table->string('concentrator', 3);
            $table->string('flow_rate')->nullable();
            $table->string('indication')->nullable();
            $table->timestamp('administered_at');
            $table->date('billing_date')->nullable();
            $table->timestamps();
            $table->index(['admission_id', 'administered_at']);
            $table->index(['admission_id', 'billing_date']);
        });

        Schema::create('admission_nebulizer_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('used_at');
            $table->unsignedSmallInteger('usage_number');
            $table->unsignedSmallInteger('billable_block')->nullable();
            $table->timestamps();
            $table->index(['admission_id', 'used_at']);
        });

        Schema::create('admission_dama_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->string('patient_or_representative');
            $table->string('relationship')->nullable();
            $table->text('reason');
            $table->text('risks_explained');
            $table->text('follow_up_advice');
            $table->text('additional_notes')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        Schema::create('admission_dama_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dama_record_id')->constrained('admission_dama_records')->cascadeOnDelete();
            $table->string('name');
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_dama_witnesses');
        Schema::dropIfExists('admission_dama_records');
        Schema::dropIfExists('admission_nebulizer_uses');
        Schema::dropIfExists('admission_oxygen_administrations');
        Schema::dropIfExists('admission_fluid_entries');
        Schema::dropIfExists('admission_sugar_checks');
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn(['oxygen_daily_fee', 'nebulizer_fee_per_three_uses']);
        });
    }
};
