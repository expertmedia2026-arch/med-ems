<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $laboratoryId = DB::table('departments')->whereRaw('LOWER(name) = ?', ['laboratory'])->value('id');
        if (! $laboratoryId) {
            $laboratoryId = DB::table('departments')->insertGetId([
                'name' => 'Laboratory',
                'description' => 'Laboratory investigations and diagnostic services.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('lab_tests')->whereNull('department_id')->update(['department_id' => $laboratoryId]);

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropUnique('lab_tests_name_unique');
            $table->dropUnique('lab_tests_code_unique');
            $table->unique(['department_id', 'name']);
            $table->unique(['department_id', 'code']);
        });

        Schema::create('department_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 80)->nullable();
            $table->string('unit', 40);
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['department_id', 'name']);
            $table->index(['department_id', 'is_active']);
        });

        Schema::create('department_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->string('reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['department_inventory_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_inventory_movements');
        Schema::dropIfExists('department_inventory_items');

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropUnique(['department_id', 'name']);
            $table->dropUnique(['department_id', 'code']);
            $table->unique('name');
            $table->unique('code');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
