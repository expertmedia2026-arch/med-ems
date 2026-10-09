<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_attachments');
    }
};