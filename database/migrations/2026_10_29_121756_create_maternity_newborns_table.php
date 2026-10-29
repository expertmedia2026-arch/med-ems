<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Store one ordered birth outcome per baby and link live births to patient records when available. */
    public function up(): void
    {
        Schema::create('maternity_newborns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maternity_episode_id')->constrained()->cascadeOnDelete();
            // A non-live outcome has no patient account; each linked patient is unique to one birth record.
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

    /** Drop newborn records before the parent episode table is rolled back. */
    public function down(): void
    {
        Schema::dropIfExists('maternity_newborns');
    }
};
