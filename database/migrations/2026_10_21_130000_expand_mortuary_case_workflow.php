<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->string('body_tag_number', 80)->nullable()->unique();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('released_to', 150)->nullable();
            $table->string('recipient_relationship', 100)->nullable();
            $table->string('recipient_id_reference', 120)->nullable();
            $table->string('release_authority', 150)->nullable();
            $table->text('release_note')->nullable();
        });

        Schema::create('mortuary_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortuary_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->timestamp('occurred_at');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['mortuary_case_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortuary_case_events');

        Schema::table('mortuary_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropUnique(['body_tag_number']);
            $table->dropColumn([
                'body_tag_number', 'released_at', 'released_to', 'recipient_relationship',
                'recipient_id_reference', 'release_authority', 'release_note',
            ]);
        });
    }
};
