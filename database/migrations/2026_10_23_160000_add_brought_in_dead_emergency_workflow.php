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
            $table->boolean('is_brought_in_dead')->default(false);
        });

        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->foreignId('mortuary_transfer_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mortuary_transfer_approved_at')->nullable();
            $table->text('mortuary_transfer_approval_note')->nullable();
        });

        DB::table('mortuary_cases')
            ->where('source', 'hospital')
            ->where('release_route', 'mortuary_transfer')
            ->whereNotNull('declared_by')
            ->update([
                'mortuary_transfer_approved_by' => DB::raw('declared_by'),
                'mortuary_transfer_approved_at' => DB::raw('COALESCE(declared_at, deceased_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mortuary_transfer_approved_by');
            $table->dropColumn(['mortuary_transfer_approved_at', 'mortuary_transfer_approval_note']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('is_brought_in_dead');
        });
    }
};
