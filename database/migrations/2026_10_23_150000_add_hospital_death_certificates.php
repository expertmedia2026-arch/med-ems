<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->string('death_certificate_number', 40)->nullable()->unique();
            $table->timestamp('death_certificate_issued_at')->nullable();
            $table->foreignId('death_certificate_issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('death_certificate_issuer_name', 150)->nullable();
            $table->string('death_certificate_registration_number', 100)->nullable();
            $table->text('death_certificate_cause')->nullable();
            $table->text('death_certificate_other_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('death_certificate_issued_by');
            $table->dropUnique(['death_certificate_number']);
            $table->dropColumn([
                'death_certificate_number', 'death_certificate_issued_at', 'death_certificate_issuer_name',
                'death_certificate_registration_number', 'death_certificate_cause', 'death_certificate_other_conditions',
            ]);
        });
    }
};
