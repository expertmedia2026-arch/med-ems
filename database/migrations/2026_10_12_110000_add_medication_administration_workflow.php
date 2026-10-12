<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->boolean('requires_in_facility_administration')->default(false);
            $table->unsignedSmallInteger('required_administration_count')->default(1);
            $table->foreignId('administration_waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('administration_waived_at')->nullable();
            $table->text('administration_waiver_reason')->nullable();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('discharged_at')->nullable();
        });

        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->string('site')->nullable();
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('administered_at')->nullable();
            $table->timestamps();
            $table->index(['visit_id', 'status']);
            $table->index(['prescription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');

        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discharged_by');
            $table->dropColumn('discharged_at');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('administration_waived_by');
            $table->dropColumn([
                'requires_in_facility_administration', 'required_administration_count',
                'administration_waived_at', 'administration_waiver_reason',
            ]);
        });
    }
};
