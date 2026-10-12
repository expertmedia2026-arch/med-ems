<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reassignments');
    }
};
