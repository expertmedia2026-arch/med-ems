<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('users')->cascadeOnDelete();
            $table->date('duty_date');
            $table->string('shift', 12);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['nurse_id', 'duty_date']);
            $table->index(['duty_date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_duties');
    }
};
