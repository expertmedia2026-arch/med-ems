<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('hospital_number')->unique();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('gender', 10);
            $t->date('date_of_birth');
            $t->string('phone', 20)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('genotype', 5)->nullable();
            $t->text('allergies')->nullable();
            $t->string('next_of_kin_name')->nullable();
            $t->string('next_of_kin_phone', 20)->nullable();
            $t->timestamps();

            $t->index(['last_name', 'first_name']);
        });

        Schema::create('drugs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('generic_name')->nullable();
            $t->string('form', 30)->nullable();      // tablet, syrup, injection...
            $t->string('strength', 30)->nullable();  // 500mg, 120mg/5ml...
            $t->decimal('unit_price', 10, 2);
            $t->unsignedInteger('stock_quantity')->default(0);
            $t->unsignedInteger('reorder_level')->default(10);
            $t->timestamps();
        });

        Schema::create('lab_tests', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->decimal('price', 10, 2);
            $t->timestamps();
        });

        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $t->dateTime('scheduled_at');
            $t->string('reason')->nullable();
            $t->string('status')->default('scheduled');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('scheduled_at');
        });

        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('waiting_triage');
            $t->text('complaint')->nullable();
            $t->text('diagnosis')->nullable();
            $t->text('clinical_notes')->nullable();
            $t->timestamp('checked_in_at')->useCurrent();
            $t->timestamp('consulted_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'checked_in_at']);
        });

        Schema::create('vitals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('recorded_by')->constrained('users');
            $t->decimal('temperature', 4, 1)->nullable();   // °C
            $t->unsignedSmallInteger('pulse')->nullable();  // bpm
            $t->unsignedSmallInteger('respiratory_rate')->nullable();
            $t->unsignedSmallInteger('bp_systolic')->nullable();
            $t->unsignedSmallInteger('bp_diastolic')->nullable();
            $t->unsignedTinyInteger('spo2')->nullable();    // %
            $t->decimal('weight', 5, 2)->nullable();        // kg
            $t->decimal('height', 5, 2)->nullable();        // cm
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('drug_id')->constrained();
            $t->foreignId('prescribed_by')->constrained('users');
            $t->string('dosage');                // e.g. 1 tablet
            $t->string('frequency');             // e.g. twice daily
            $t->string('duration');              // e.g. 5 days
            $t->unsignedInteger('quantity');
            $t->text('instructions')->nullable();
            $t->string('status')->default('pending');
            $t->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dispensed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('lab_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lab_test_id')->constrained();
            $t->foreignId('requested_by')->constrained('users');
            $t->string('status')->default('pending');
            $t->text('result')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_number')->unique();
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('amount_paid', 12, 2)->default(0);
            $t->string('status')->default('unpaid');
            $t->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('category', 20);          // consultation | lab | drug
            $t->string('description');
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('unit_price', 10, 2);
            $t->decimal('total', 12, 2);
            $t->nullableMorphs('billable');      // links back to LabRequest / Prescription
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('method', 20);            // cash | transfer | pos
            $t->string('reference')->nullable();
            $t->foreignId('received_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'payments', 'invoice_items', 'invoices', 'lab_requests', 'prescriptions',
            'vitals', 'visits', 'appointments', 'lab_tests', 'drugs', 'patients',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};