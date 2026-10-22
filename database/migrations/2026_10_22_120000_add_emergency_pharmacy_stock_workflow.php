<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_pharmacy_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->timestamps();
        });

        Schema::create('emergency_pharmacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('emergency_pharmacy_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('emergency_pharmacy_requests')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_medication_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->string('source', 20);
            $table->string('dosage')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_medication_uses');
        Schema::dropIfExists('emergency_pharmacy_movements');
        Schema::dropIfExists('emergency_pharmacy_requests');
        Schema::dropIfExists('emergency_pharmacy_stocks');
    }
};
