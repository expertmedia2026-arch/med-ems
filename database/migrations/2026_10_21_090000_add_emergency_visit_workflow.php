<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('is_emergency')->default(false)->after('type');
            $table->string('arrival_mode', 30)->nullable()->after('is_emergency');
            $table->foreignId('registered_by')->nullable()->after('arrival_mode')->constrained('users')->nullOnDelete();
            $table->string('triage_priority', 20)->nullable()->after('arrival_mode');
            $table->timestamp('triaged_at')->nullable()->after('triage_priority');
            $table->string('disposition', 20)->nullable()->after('triaged_at');
            $table->text('disposition_note')->nullable()->after('disposition');
            $table->index(['is_emergency', 'status', 'checked_in_at']);
        });

        DB::table('departments')->updateOrInsert(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['is_emergency', 'status', 'checked_in_at']);
            $table->dropConstrainedForeignId('registered_by');
            $table->dropColumn(['is_emergency', 'arrival_mode', 'triage_priority', 'triaged_at', 'disposition', 'disposition_note']);
        });

        $departmentId = DB::table('departments')->where('name', 'Accident & Emergency')->value('id');
        if ($departmentId && ! DB::table('visits')->where('department_id', $departmentId)->exists()
            && ! DB::table('users')->where('department_id', $departmentId)->exists()) {
            DB::table('departments')->where('id', $departmentId)->delete();
        }
    }
};
