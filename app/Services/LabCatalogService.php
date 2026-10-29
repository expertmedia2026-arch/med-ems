<?php

namespace App\Services;

use App\Models\LabTest;
use App\Models\Department;
use App\Models\User;
use App\Enums\Role;

/**
 * Supplies laboratory catalog screens and manages department-owned test definitions.
 *
 * Laboratory users are restricted to their own department; administrators can manage tests
 * across the tenant and optionally narrow catalog listings to one department.
 */
class LabCatalogService
{
    private const CATEGORIES = [
        'Haematology', 'Chemistry', 'Microbiology', 'Parasitology',
        'Serology', 'Urinalysis', 'Hormones', 'Other',
    ];

    private const SAMPLES = ['Blood', 'Urine', 'Stool', 'Sputum', 'Swab', 'Other'];

    /**
     * Return a filtered, paginated test catalog and its category and department options.
     *
     * Filters may include department, search term, category, and active status. A laboratory user
     * without a department is rejected rather than being shown an unscoped catalog.
     *
     * @param  array<string, mixed>  $filters
     * @return array{tests: \Illuminate\Contracts\Pagination\LengthAwarePaginator, categories: \Illuminate\Support\Collection, departments: \Illuminate\Database\Eloquent\Collection}
     */
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

    /**
     * Return the fixed form choices and department selection for creating or editing a test.
     *
     * Laboratory staff receive their assigned department as the selected value; other users
     * default to the edited test's department when one is supplied.
     *
     * @return array{categories: array<int, string>, samples: array<int, string>, departments: \Illuminate\Database\Eloquent\Collection, selectedDepartmentId: int|null}
     */
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

    /**
     * Create an active laboratory test after checking permission for its target department.
     *
     * The supplied attributes are expected to be validated by the request layer; the stored code
     * is uppercased when present.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): LabTest
    {
        $this->authorizeDepartment($attributes, $user);
        $attributes['is_active'] = true;

        return LabTest::create($this->normalize($attributes));
    }

    /**
     * Update a test's descriptive fields without changing its active/inactive state.
     *
     * The actor must manage the test's existing department and the requested target department.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(LabTest $test, array $attributes, User $user): LabTest
    {
        $this->authorizeTest($test, $user);
        $this->authorizeDepartment($attributes, $user);
        $attributes['is_active'] = $test->is_active;
        $test->update($this->normalize($attributes));

        return $test->refresh();
    }

    /**
     * Flip a test between active and inactive after verifying department ownership.
     */
    public function toggle(LabTest $test, User $user): LabTest
    {
        $this->authorizeTest($test, $user);
        $test->update(['is_active' => ! $test->is_active]);

        return $test->refresh();
    }

    /**
     * Enforce that a catalog record belongs to an administrator or the laboratory user's department.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException When the user cannot manage the test.
     */
    public function authorizeTest(LabTest $test, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin) || ($user->hasRole(Role::Lab) && (int) $user->department_id === (int) $test->department_id), 403);
    }

    /**
     * Enforce access to the department selected for a new or moved laboratory test.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function authorizeDepartment(array $attributes, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin) || ($user->hasRole(Role::Lab) && (int) $user->department_id === (int) ($attributes['department_id'] ?? 0)), 403);
    }

    /**
     * Normalize a submitted catalog code to uppercase while leaving other fields unchanged.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (! empty($attributes['code'])) {
            $attributes['code'] = strtoupper($attributes['code']);
        }

        return $attributes;
    }
}
