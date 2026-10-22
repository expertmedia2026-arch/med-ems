<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('hod_id')->nullable()->unique()->constrained('users')->nullOnDelete();
        });

        Schema::create('staff_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('duty_date');
            $table->string('shift', 12);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'duty_date']);
            $table->index(['duty_date', 'shift']);
        });

        if (Schema::hasTable('nurse_duties')) {
            DB::table('nurse_duties')->orderBy('id')->cursor()->each(function ($duty) {
                DB::table('staff_duties')->insertOrIgnore([
                    'user_id' => $duty->nurse_id,
                    'duty_date' => $duty->duty_date,
                    'shift' => $duty->shift,
                    'assigned_by' => $duty->assigned_by,
                    'created_at' => $duty->created_at,
                    'updated_at' => $duty->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_duties');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hod_id');
        });
    }
};
