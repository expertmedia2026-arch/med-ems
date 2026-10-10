<?php

namespace App\Services;

use App\Models\LabTest;

class LabCatalogService
{
    private const CATEGORIES = [
        'Haematology', 'Chemistry', 'Microbiology', 'Parasitology',
        'Serology', 'Urinalysis', 'Hormones', 'Other',
    ];

    private const SAMPLES = ['Blood', 'Urine', 'Stool', 'Sputum', 'Swab', 'Other'];

    public function indexData(array $filters): array
    {
        $tests = LabTest::query()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('code', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return [
            'tests' => $tests,
            'categories' => collect(self::CATEGORIES)
                ->merge(LabTest::whereNotNull('category')->distinct()->pluck('category'))
                ->unique()->sort()->values(),
        ];
    }

    public function formData(): array
    {
        return ['categories' => self::CATEGORIES, 'samples' => self::SAMPLES];
    }

    public function create(array $attributes): LabTest
    {
        $attributes['is_active'] = true;

        return LabTest::create($this->normalize($attributes));
    }

    public function update(LabTest $test, array $attributes): LabTest
    {
        $attributes['is_active'] = $test->is_active;
        $test->update($this->normalize($attributes));

        return $test->refresh();
    }

    public function toggle(LabTest $test): LabTest
    {
        $test->update(['is_active' => ! $test->is_active]);

        return $test->refresh();
    }

    private function normalize(array $attributes): array
    {
        if (! empty($attributes['code'])) {
            $attributes['code'] = strtoupper($attributes['code']);
        }

        return $attributes;
    }
}
