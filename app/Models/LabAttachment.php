<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class LabAttachment extends Model
{
    protected $guarded = [];

    protected function isImage(): Attribute
    {
        return Attribute::get(fn () => str_starts_with($this->mime, 'image/'));
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => route('lab.attachments.show', $this));
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(fn () => Number::fileSize($this->size));
    }

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}