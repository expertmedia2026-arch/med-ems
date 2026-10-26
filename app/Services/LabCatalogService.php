<?php

namespace App\Services;

use App\Models\LabTest;
use App\Models\Department;
use App\Models\User;
use App\Enums\Role;

class LabCatalogService
{
    private const CATEGORIES = [
        'Haematology', 'Chemistry', 'Microbiology', 'Parasitology',
        'Serology', 'Urinalysis', 'Hormones', 'Other',
    ];

    private const SAMPLES = ['Blood', 'Urine', 'Stool', 'Sputum', 'Swab', 'Other'];

    public function indexData(array $filters, ?User $user = null): array
    {
        abort_if($user?->hasRole(Role::Lab) && ! $user->department_id, 403, 'Assign this staff member to a department first.');

        $tests = LabTest::query()
            ->with('department:id,name')
            ->when($user?->hasRole(Role::Lab), fn ($query) => $query->where('department_id', $user->department_id ?? 0))
            ->when($user?->hasRole(Role::Admin) && ($filters['department_id'] ?? null), fn ($query) => $query->where('department_id', $filters['department_id']))
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
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function formData(?User $user = null, ?LabTest $test = null): array
    {
        abort_if($user?->hasRole(Role::Lab) && ! $user->department_id, 403, 'Assign this staff member to a department first.');

        return [
            'categories' => self::CATEGORIES,
            'samples' => self::SAMPLES,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'selectedDepartmentId' => $user?->hasRole(Role::Lab) ? $user->department_id : ($test?->department_id),
        ];
    }

    public function create(array $attributes, User $user): LabTest
    {
        $this->authorizeDepartment($attributes, $user);
        $attributes['is_active'] = true;

        return LabTest::create($this->normalize($attributes));
    }

    public function update(LabTest $test, array $attributes, User $user): LabTest
    {
        $this->authorizeTest($test, $user);
        $this->authorizeDepartment($attributes, $user);
        $attributes['is_active'] = $test->is_active;
        $test->update($this->normalize($attributes));

        return $test->refresh();
    }

    public function toggle(LabTest $test, User $user): LabTest
    {
        $this->authorizeTest($test, $user);
        $test->update(['is_active' => ! $test->is_active]);

        return $test->refresh();
    }

    public function authorizeTest(LabTest $test, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin) || ($user->hasRole(Role::Lab) && (int) $user->department_id === (int) $test->department_id), 403);
    }

    private function authorizeDepartment(array $attributes, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin) || ($user->hasRole(Role::Lab) && (int) $user->department_id === (int) ($attributes['department_id'] ?? 0)), 403);
    }

    private function normalize(array $attributes): array
    {
        if (! empty($attributes['code'])) {
            $attributes['code'] = strtoupper($attributes['code']);
        }

        return $attributes;
    }
}
