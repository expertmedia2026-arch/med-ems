<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalWorkflow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public static function activeDefinition(string $workflow): array
    {
        $workflowRecord = static::query()
            ->where('workflow', $workflow)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        return is_array($workflowRecord?->definition) ? $workflowRecord->definition : [];
    }

    public static function sectionEnabled(string $workflow, string $section): bool
    {
        $sections = static::activeDefinition($workflow)['sections'] ?? null;
        if (! is_array($sections)) {
            return true;
        }

        foreach ($sections as $item) {
            if (($item['key'] ?? null) === $section) {
                return (bool) ($item['enabled'] ?? false);
            }
        }

        return false;
    }
}
