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
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('facility_type', 30)->default('hospital')->index();
        });

        Schema::create('drug_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_remaining');
            $table->date('expiry_date');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['hospital_id', 'expiry_date']);
            $table->index(['drug_id', 'quantity_remaining', 'expiry_date']);
        });

        Schema::create('pharmacy_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('sale_number')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('payment_method', 20);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('status', 20)->default('completed');
            $table->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'sold_at']);
            $table->index(['hospital_id', 'status', 'sold_at']);
        });

        Schema::create('pharmacy_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->json('batch_allocations')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'drug_id']);
        });

        Schema::table('drug_stock_movements', function (Blueprint $table) {
            $table->foreignId('pharmacy_sale_item_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drug_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacy_sale_item_id');
        });

        Schema::dropIfExists('pharmacy_sale_items');
        Schema::dropIfExists('pharmacy_sales');
        Schema::dropIfExists('drug_stock_batches');

        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropIndex(['facility_type']);
            $table->dropColumn('facility_type');
        });
    }
};
