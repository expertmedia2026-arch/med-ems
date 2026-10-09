<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->string('receipt_number', 30)->nullable()->unique();
        });

        Schema::table('invoice_items', function (Blueprint $t) {
            $t->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('payment_id');
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique(['receipt_number']);
            $t->dropColumn('receipt_number');
        });
    }
};