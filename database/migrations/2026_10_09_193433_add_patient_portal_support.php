<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A portal login belongs to one patient record
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('patient_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('patients', function (Blueprint $t) {
            $t->timestamp('registration_paid_at')->nullable();
        });

        // Everyone registered before this feature counts as already registered
        DB::table('patients')->update(['registration_paid_at' => now()]);

        // The registration fee is billed to the patient, not to a visit
        Schema::table('invoices', function (Blueprint $t) {
            $t->unsignedBigInteger('visit_id')->nullable()->change();
        });

        Schema::create('patient_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('email')->index();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        Schema::table('hospital_settings', function (Blueprint $t) {
            $t->decimal('registration_fee', 10, 2)->default(2000);
        });
    }

    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $t) {
            $t->dropColumn('registration_fee');
        });

        Schema::dropIfExists('patient_invitations');

        // invoices.visit_id stays nullable: reverting it would fail if registration invoices exist

        Schema::table('patients', function (Blueprint $t) {
            $t->dropColumn('registration_paid_at');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('patient_id');
        });
    }
};