<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->foreignId('declared_by')->nullable()->after('opened_by')->constrained('users')->nullOnDelete();
            $table->timestamp('declared_at')->nullable()->after('deceased_at');
            $table->text('cause_of_death')->nullable();
            $table->text('death_clinical_summary')->nullable();
            $table->string('release_route', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declared_by');
            $table->dropColumn(['declared_at', 'cause_of_death', 'death_clinical_summary', 'release_route']);
        });
    }
};
