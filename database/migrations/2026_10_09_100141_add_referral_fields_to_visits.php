<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $t) {
            $t->string('type', 20)->default('consultation')->index();   // consultation | lab_only
            $t->string('referred_from')->nullable();
            $t->string('referred_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $t) {
            $t->dropIndex(['type']);
            $t->dropColumn(['type', 'referred_from', 'referred_by']);
        });
    }
};