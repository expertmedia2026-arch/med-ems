<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->string('payment_gateway', 30)->default('paystack');
            $table->string('primary_color', 7)->default('#0f766e');
            $table->string('secondary_color', 7)->default('#1d4ed8');
            $table->string('accent_color', 7)->nullable();
            $table->string('highlight_color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway', 'primary_color', 'secondary_color', 'accent_color', 'highlight_color',
            ]);
        });
    }
};
