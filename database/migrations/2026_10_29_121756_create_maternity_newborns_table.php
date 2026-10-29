<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maternity_newborns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maternity_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('birth_order');
            $table->string('outcome', 20);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth');
            $table->timestamps();

            $table->unique(['maternity_episode_id', 'birth_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maternity_newborns');
    }
};
