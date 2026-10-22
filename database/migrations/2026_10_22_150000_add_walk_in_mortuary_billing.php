<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->decimal('mortuary_intake_fee', 10, 2)->default(0);
            $table->decimal('mortuary_daily_storage_fee', 10, 2)->default(0);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->change();
        });

        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->change();
            $table->foreignId('invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('hospital');
            $table->string('external_contact_name', 150)->nullable();
            $table->string('external_contact_phone', 30)->nullable();
            $table->text('external_contact_address')->nullable();
            $table->unsignedTinyInteger('deceased_age')->nullable();
            $table->decimal('intake_fee', 10, 2)->nullable();
            $table->decimal('daily_storage_fee', 10, 2)->nullable();
            $table->timestamp('storage_billed_through')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn([
                'source', 'external_contact_name', 'external_contact_phone', 'external_contact_address',
                'deceased_age', 'intake_fee', 'daily_storage_fee', 'storage_billed_through',
            ]);
        });

        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn(['mortuary_intake_fee', 'mortuary_daily_storage_fee']);
        });

        // Visit links remain nullable so existing external cases are not destroyed by rollback.
    }
};
