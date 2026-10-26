<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabTestIndexRequest;
use App\Http\Requests\SaveLabTestRequest;
use App\Models\LabTest;
use App\Services\LabCatalogService;
use Illuminate\Http\Request;

class LabTestController extends Controller
{
    public function index(LabTestIndexRequest $request, LabCatalogService $catalog)
    {
        return view('lab.tests.index', $catalog->indexData($request->validated(), $request->user()));
    }

    public function create(Request $request, LabCatalogService $catalog)
    {
        return view('lab.tests.create', $catalog->formData($request->user()));
    }

    public function store(SaveLabTestRequest $request, LabCatalogService $catalog)
    {
        $test = $catalog->create($request->validated(), $request->user());

        return redirect()->route('lab.tests.index')->with('success', "{$test->name} added to the catalog.");
    }

    public function edit(Request $request, LabTest $labTest, LabCatalogService $catalog)
    {
        $catalog->authorizeTest($labTest, $request->user());

        return view('lab.tests.edit', ['test' => $labTest] + $catalog->formData($request->user(), $labTest));
    }

    public function update(SaveLabTestRequest $request, LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->update($labTest, $request->validated(), $request->user());

        return redirect()->route('lab.tests.index')->with('success', "{$labTest->name} updated.");
    }

    public function toggle(LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->toggle($labTest, request()->user());

        return back()->with('success', $labTest->name.($labTest->is_active ? ' activated.' : ' deactivated. Doctors can no longer request it.'));
    }
}
