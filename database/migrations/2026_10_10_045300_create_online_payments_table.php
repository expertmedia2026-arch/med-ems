<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per attempt to pay online
        Schema::create('online_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 60)->unique();
            $t->string('gateway', 20)->default('paystack');
            $t->decimal('amount', 12, 2);
            $t->json('item_ids');
            $t->string('status', 20)->default('pending');   // pending | success | failed | review
            $t->string('note')->nullable();
            $t->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        // Online payments are not received by a staff member
        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('received_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};