<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hospital_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('module', 80);
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'module']);
        });

        Schema::create('hospital_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('workflow', 80);
            $table->unsignedInteger('version')->default(1);
            $table->json('definition');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'workflow', 'version']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_global_admin')->default(false)->after('is_active');
        });

        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique('hospital_id');
        });

        $legacySettings = DB::table('hospital_settings')->orderBy('id')->first();
        $name = $legacySettings?->name ?: config('app.name', 'Hospital');
        $slug = Str::slug($name) ?: 'hospital';
        $hospitalId = DB::table('hospitals')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->whereNull('hospital_id')->update(['hospital_id' => $hospitalId]);
        DB::table('hospital_settings')->whereNull('hospital_id')->update(['hospital_id' => $hospitalId]);

        $tenantTables = [
            'patients', 'drugs', 'lab_tests', 'appointments', 'visits', 'vitals', 'prescriptions',
            'lab_requests', 'invoices', 'invoice_items', 'payments', 'staff_invitations',
            'lab_attachments', 'patient_invitations', 'online_payments', 'departments',
            'doctor_availabilities', 'consultation_types', 'appointment_reassignments',
            'prescription_stock_reservations', 'drug_stock_movements', 'medication_administrations',
            'nurse_duties', 'admissions', 'admission_notes', 'admission_vitals', 'mortuary_cases',
            'emergency_observations', 'emergency_progress_notes', 'mortuary_case_events',
            'emergency_pharmacy_stocks', 'emergency_pharmacy_requests', 'emergency_pharmacy_movements',
            'emergency_medication_uses', 'emergency_pharmacy_items', 'emergency_pharmacy_item_movements',
            'emergency_item_uses', 'department_inventory_items', 'department_inventory_movements',
            'staff_duties', 'admission_sugar_checks', 'admission_fluid_entries',
            'admission_oxygen_administrations', 'admission_nebulizer_uses', 'admission_dama_records',
            'admission_dama_witnesses', 'admission_insulin_machine_blocks',
            'admission_insulin_machine_uses', 'appointment_lab_test',
        ];

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'hospital_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('hospital_id')->nullable()->index();
            });

            DB::table($tableName)->whereNull('hospital_id')->update(['hospital_id' => $hospitalId]);
        }

        foreach (['pharmacy', 'mortuary', 'emergency', 'inpatient', 'laboratory'] as $module) {
            DB::table('hospital_modules')->insert([
                'hospital_id' => $hospitalId,
                'module' => $module,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('hospital_workflows')->insert([
            'hospital_id' => $hospitalId,
            'workflow' => 'admission',
            'version' => 1,
            'definition' => json_encode([
                'template' => 'current',
                'sections' => [
                    ['key' => 'admission_details', 'enabled' => true, 'required' => true],
                    ['key' => 'vitals', 'enabled' => true, 'required' => true],
                    ['key' => 'care_notes', 'enabled' => true, 'required' => false],
                    ['key' => 'sugar_checks', 'enabled' => true, 'required' => false],
                    ['key' => 'fluid_balance', 'enabled' => true, 'required' => false],
                    ['key' => 'oxygen', 'enabled' => true, 'required' => false],
                    ['key' => 'nebulizer', 'enabled' => true, 'required' => false],
                    ['key' => 'insulin_machine', 'enabled' => true, 'required' => false],
                ],
                'custom_fields' => [],
            ]),
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropUnique(['hospital_id']);
            $table->dropConstrainedForeignId('hospital_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropColumn('is_global_admin');
        });

        Schema::dropIfExists('hospital_workflows');
        Schema::dropIfExists('hospital_modules');
        Schema::dropIfExists('hospitals');
    }
};
