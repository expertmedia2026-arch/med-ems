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
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->string('paystack_public_key')->nullable();
            $table->text('paystack_secret_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn(['paystack_public_key', 'paystack_secret_key']);
        });
    }
};
