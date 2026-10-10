<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $t) {
            $t->boolean('dob_estimated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $t) {
            $t->dropColumn('dob_estimated');
        });
    }
};