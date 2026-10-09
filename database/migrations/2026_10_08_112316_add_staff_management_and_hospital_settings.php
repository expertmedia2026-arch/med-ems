<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
        });

        Schema::create('staff_invitations', function (Blueprint $t) {
            $t->id();
            $t->string('email')->index();
            $t->string('role');
            $t->string('token_hash', 64)->unique();
            $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('hospital_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('address')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('email')->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('receipt_footer')->nullable();
            $t->timestamps();
        });

        DB::table('hospital_settings')->insert([
            'name'       => config('app.name', 'Hospital'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_settings');
        Schema::dropIfExists('staff_invitations');

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_active');
        });
    }
};