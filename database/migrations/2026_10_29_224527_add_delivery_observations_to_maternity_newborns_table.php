<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add optional delivery-room measurements to each newborn record. */
    public function up(): void
    {
        Schema::table('maternity_newborns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('birth_weight_grams')->nullable();
            $table->unsignedTinyInteger('apgar_score_1_minute')->nullable();
            $table->unsignedTinyInteger('apgar_score_5_minutes')->nullable();
        });
    }

    /** Remove the delivery-room measurements. */
    public function down(): void
    {
        Schema::table('maternity_newborns', function (Blueprint $table): void {
            $table->dropColumn([
                'birth_weight_grams',
                'apgar_score_1_minute',
                'apgar_score_5_minutes',
            ]);
        });
    }
};
