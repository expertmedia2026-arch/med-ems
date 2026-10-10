<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabTestIndexRequest;
use App\Http\Requests\SaveLabTestRequest;
use App\Models\LabTest;
use App\Services\LabCatalogService;

class LabTestController extends Controller
{
    public function index(LabTestIndexRequest $request, LabCatalogService $catalog)
    {
        return view('lab.tests.index', $catalog->indexData($request->validated()));
    }

    public function create(LabCatalogService $catalog)
    {
        return view('lab.tests.create', $catalog->formData());
    }

    public function store(SaveLabTestRequest $request, LabCatalogService $catalog)
    {
        $test = $catalog->create($request->validated());

        return redirect()->route('lab.tests.index')->with('success', "{$test->name} added to the catalog.");
    }

    public function edit(LabTest $labTest, LabCatalogService $catalog)
    {
        return view('lab.tests.edit', ['test' => $labTest] + $catalog->formData());
    }

    public function update(SaveLabTestRequest $request, LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->update($labTest, $request->validated());

        return redirect()->route('lab.tests.index')->with('success', "{$labTest->name} updated.");
    }

    public function toggle(LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->toggle($labTest);

        return back()->with('success', $labTest->name.($labTest->is_active ? ' activated.' : ' deactivated. Doctors can no longer request it.'));
    }
}
