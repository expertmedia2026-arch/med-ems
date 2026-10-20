<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('has_ward')->default(false);
        });

        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 32)->nullable()->unique();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('attending_doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admitted_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('admitted_at');
            $table->text('admission_reason');
            $table->text('admission_diagnosis');
            $table->text('initial_treatment_plan');
            $table->date('expected_discharge_date')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('discharged_at')->nullable();
            $table->text('discharge_diagnosis')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->text('discharge_medications')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->text('patient_discharge_instructions')->nullable();
            $table->text('pending_results_plan')->nullable();
            $table->timestamps();

            $table->index(['status', 'department_id']);
            $table->index(['status', 'attending_doctor_id']);
        });

        Schema::table('lab_requests', function (Blueprint $table) {
            $table->foreignId('result_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('result_reviewed_at')->nullable();
            $table->text('result_review_note')->nullable();
        });

        Schema::create('admission_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('note_type', 20);
            $table->text('note');
            $table->timestamps();
            $table->index(['admission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lab_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_reviewed_by');
            $table->dropColumn(['result_reviewed_at', 'result_review_note']);
        });

        Schema::dropIfExists('admission_notes');

        Schema::dropIfExists('admissions');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('has_ward');
        });
    }
};
