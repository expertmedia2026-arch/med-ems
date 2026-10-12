<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->unsignedInteger('reserved_quantity')->default(0);
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedInteger('dispensable_quantity')->nullable();
        });

        DB::table('prescriptions')->whereNull('dispensable_quantity')->update([
            'dispensable_quantity' => DB::raw('quantity'),
        ]);

        Schema::create('prescription_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['drug_id', 'status']);
        });

        Schema::create('drug_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('drugs')->orderBy('id')->get(['id', 'stock_quantity']) as $drug) {
            if ($drug->stock_quantity > 0) {
                DB::table('drug_stock_movements')->insert([
                    'drug_id' => $drug->id,
                    'type' => 'opening_balance',
                    'quantity_change' => $drug->stock_quantity,
                    'stock_before' => 0,
                    'stock_after' => $drug->stock_quantity,
                    'notes' => 'Opening balance when stock reservations were introduced.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $available = (int) $drug->stock_quantity;
            $reserved = 0;
            $paidItems = DB::table('invoice_items')
                ->select('billable_id')
                ->selectRaw('MAX(CASE WHEN payment_id IS NOT NULL THEN 1 ELSE 0 END) AS is_paid')
                ->where('billable_type', '=', 'App\\Models\\Prescription')
                ->groupBy('billable_id');
            $prescriptions = DB::table('prescriptions')
                ->leftJoinSub($paidItems, 'prescription_payment_status', function ($join) {
                    $join->on('prescription_payment_status.billable_id', '=', 'prescriptions.id');
                })
                ->where('prescriptions.drug_id', $drug->id)
                ->where('prescriptions.status', 'pending')
                ->orderByDesc('prescription_payment_status.is_paid')
                ->orderBy('prescriptions.created_at')
                ->orderBy('prescriptions.id')
                ->get([
                    'prescriptions.id', 'prescriptions.visit_id', 'prescriptions.quantity',
                    'prescription_payment_status.is_paid',
                ]);

            foreach ($prescriptions as $prescription) {
                $quantity = (int) $prescription->quantity;

                if ($quantity < 1 || $quantity > $available) {
                    continue;
                }

                $expiresAt = $prescription->is_paid
                    ? null
                    : now()->addMinutes((int) config('hms.pharmacy.unpaid_reservation_minutes', 120));

                DB::table('prescription_stock_reservations')->insert([
                    'prescription_id' => $prescription->id,
                    'visit_id' => $prescription->visit_id,
                    'drug_id' => $drug->id,
                    'quantity' => $quantity,
                    'status' => 'reserved',
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $available -= $quantity;
                $reserved += $quantity;
            }

            DB::table('drugs')->where('id', $drug->id)->update(['reserved_quantity' => $reserved]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_stock_movements');
        Schema::dropIfExists('prescription_stock_reservations');

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('dispensable_quantity');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });
    }
};
