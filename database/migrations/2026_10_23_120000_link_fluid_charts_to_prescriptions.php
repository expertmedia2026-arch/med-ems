<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->boolean('is_fluid')->default(false);
        });

        Schema::table('admission_fluid_entries', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('admission_id')->constrained()->nullOnDelete();
            $table->index(['admission_id', 'prescription_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admission_fluid_entries', function (Blueprint $table) {
            $table->dropIndex(['admission_id', 'prescription_id']);
            $table->dropConstrainedForeignId('prescription_id');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('is_fluid');
        });
    }
};
