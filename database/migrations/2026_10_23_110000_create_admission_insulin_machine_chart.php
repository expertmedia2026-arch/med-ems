<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_insulin_machine_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('block_number');
            $table->decimal('fee', 10, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamps();
            $table->unique(['admission_id', 'block_number']);
        });

        Schema::create('admission_insulin_machine_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('admission_insulin_machine_blocks')->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('bottle_label', 100)->nullable();
            $table->timestamp('opened_at');
            $table->timestamps();
            $table->index(['admission_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_insulin_machine_uses');
        Schema::dropIfExists('admission_insulin_machine_blocks');
    }
};
