<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_pharmacy_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 40)->default('consumable');
            $table->string('unit', 40)->default('unit');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('emergency_pharmacy_item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_pharmacy_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_change')->default(0);
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_item_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_pharmacy_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->string('dose_or_amount')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_item_uses');
        Schema::dropIfExists('emergency_pharmacy_item_movements');
        Schema::dropIfExists('emergency_pharmacy_items');
    }
};
