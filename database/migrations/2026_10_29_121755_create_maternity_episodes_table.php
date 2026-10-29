<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create labor-to-postpartum records and register Maternity as an opt-in hospital module. */
    public function up(): void
    {
        Schema::create('maternity_episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            // One maternity episode belongs to one admission, preventing duplicate labor workflows.
            $table->foreignId('admission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('in_labor');
            $table->timestamp('labor_started_at');
            $table->text('labor_assessment')->nullable();
            $table->timestamp('delivery_at')->nullable();
            $table->string('delivery_mode', 30)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('postpartum_notes')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
        });

        // Existing hospitals receive Maternity as disabled so deployment does not
        // silently expose a new clinical workflow before an admin enables it.
        $now = now();
        foreach (DB::table('hospitals')->pluck('id') as $hospitalId) {
            DB::table('hospital_modules')->updateOrInsert(
                ['hospital_id' => $hospitalId, 'module' => 'maternity'],
                ['enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /** Remove the feature schema and its per-hospital module flags on rollback. */
    public function down(): void
    {
        Schema::dropIfExists('maternity_episodes');
        DB::table('hospital_modules')->where('module', 'maternity')->delete();
    }
};
