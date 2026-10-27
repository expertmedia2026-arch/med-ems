<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_name_unique');
            $table->unique(['hospital_id', 'name'], 'departments_hospital_name_unique');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_hospital_number_unique');
            $table->dropUnique('patients_phone_unique');
            $table->dropUnique('patients_email_unique');
            $table->unique(['hospital_id', 'hospital_number'], 'patients_hospital_number_by_hospital_unique');
            $table->unique(['hospital_id', 'phone'], 'patients_phone_by_hospital_unique');
            $table->unique(['hospital_id', 'email'], 'patients_email_by_hospital_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_hospital_number_by_hospital_unique');
            $table->dropUnique('patients_phone_by_hospital_unique');
            $table->dropUnique('patients_email_by_hospital_unique');
            $table->unique('hospital_number', 'patients_hospital_number_unique');
            $table->unique('phone', 'patients_phone_unique');
            $table->unique('email', 'patients_email_unique');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_hospital_name_unique');
            $table->unique('name', 'departments_name_unique');
        });
    }
};
