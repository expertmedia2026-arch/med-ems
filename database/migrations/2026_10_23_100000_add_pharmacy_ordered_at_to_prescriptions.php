<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('pharmacy_ordered_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['pharmacy_ordered_at']);
            $table->dropColumn('pharmacy_ordered_at');
        });
    }
};
