<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('identity_status', 20)->default('identified');
        });

        Schema::create('mortuary_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 32)->nullable()->unique();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('awaiting_transfer');
            $table->timestamp('deceased_at');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('storage_location', 120)->nullable();
            $table->text('handoff_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'deceased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortuary_cases');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('identity_status');
        });
    }
};
