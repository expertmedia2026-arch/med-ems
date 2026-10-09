<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $t) {
            $t->string('code', 20)->nullable()->unique();
            $t->string('category', 50)->nullable();
            $t->string('sample_type', 50)->nullable();
            $t->unsignedSmallInteger('turnaround_hours')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
        });

        Schema::table('drugs', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $t) {
            $t->dropUnique(['code']);
            $t->dropColumn(['code', 'category', 'sample_type', 'turnaround_hours', 'description', 'is_active']);
        });

        Schema::table('drugs', function (Blueprint $t) {
            $t->dropColumn('is_active');
        });
    }
};