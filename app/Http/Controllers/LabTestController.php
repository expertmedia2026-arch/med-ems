<?php

namespace App\Http\Controllers;

use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabTestController extends Controller
{
    public const CATEGORIES = [
        'Haematology', 'Chemistry', 'Microbiology', 'Parasitology',
        'Serology', 'Urinalysis', 'Hormones', 'Other',
    ];

    public const SAMPLES = ['Blood', 'Urine', 'Stool', 'Sputum', 'Swab', 'Other'];

    public function index(Request $request)
    {
        $tests = LabTest::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->query('q');
                $q->where(fn ($q) => $q->whereLike('name', "%{$term}%")
                                       ->orWhereLike('code', "%{$term}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('lab.tests.index', [
            'tests'      => $tests,
            'categories' => collect(self::CATEGORIES)
                ->merge(LabTest::whereNotNull('category')->distinct()->pluck('category'))
                ->unique()->sort()->values(),
        ]);
    }

    public function create()
    {
        return view('lab.tests.create', $this->formData());
    }

    public function store(Request $request)
    {
        $test = LabTest::create($this->validated($request));

        return redirect()->route('lab.tests.index')->with('success', "{$test->name} added to the catalog.");
    }

    public function edit(LabTest $labTest)
    {
        return view('lab.tests.edit', ['test' => $labTest] + $this->formData());
    }

    public function update(Request $request, LabTest $labTest)
    {
        $labTest->update($this->validated($request, $labTest));

        return redirect()->route('lab.tests.index')->with('success', "{$labTest->name} updated.");
    }

    public function toggle(LabTest $labTest)
    {
        $labTest->update(['is_active' => ! $labTest->is_active]);

        return back()->with('success', $labTest->name . ($labTest->is_active ? ' activated.' : ' deactivated. Doctors can no longer request it.'));
    }

    private function formData(): array
    {
        return ['categories' => self::CATEGORIES, 'samples' => self::SAMPLES];
    }

    private function validated(Request $request, ?LabTest $test = null): array
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:150', Rule::unique('lab_tests', 'name')->ignore($test?->id)],
            'code'             => ['nullable', 'string', 'max:20', Rule::unique('lab_tests', 'code')->ignore($test?->id)],
            'category'         => ['nullable', 'string', 'max:50'],
            'sample_type'      => ['nullable', 'string', 'max:50'],
            'price'            => ['required', 'numeric', 'min:0', 'max:99999999'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $test ? $test->is_active : true;

        if (! empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }
}
