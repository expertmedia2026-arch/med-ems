<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lab appointments have no doctor
        Schema::table('appointments', function (Blueprint $t) {
            $t->unsignedBigInteger('doctor_id')->nullable()->change();
        });

        Schema::table('appointments', function (Blueprint $t) {
            $t->string('type', 20)->default('consultation')->index();   // consultation | lab
        });

        Schema::create('appointment_lab_test', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lab_test_id')->constrained();
            $t->unique(['appointment_id', 'lab_test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_lab_test');

        Schema::table('appointments', function (Blueprint $t) {
            $t->dropIndex(['type']);
            $t->dropColumn('type');
        });
    }
};