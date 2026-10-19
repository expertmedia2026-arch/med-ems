<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('hospital_number');
        });

        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->string('administrator_signature_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_settings', function (Blueprint $table) {
            $table->dropColumn('administrator_signature_path');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
