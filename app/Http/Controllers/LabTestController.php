<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabTestIndexRequest;
use App\Http\Requests\SaveLabTestRequest;
use App\Models\LabTest;
use App\Services\LabCatalogService;
use Illuminate\Http\Request;

/**
 * Coordinates laboratory catalogue pages and test changes with LabCatalogService.
 */
class LabTestController extends Controller
{
    /**
     * Uses validated filters and the current user's scope to render the laboratory test catalogue.
     */
    public function index(LabTestIndexRequest $request, LabCatalogService $catalog)
    {
        return view('lab.tests.index', $catalog->indexData($request->validated(), $request->user()));
    }

    /**
     * Loads user-authorized form data for creating a laboratory test.
     */
    public function create(Request $request, LabCatalogService $catalog)
    {
        return view('lab.tests.create', $catalog->formData($request->user()));
    }

    /**
     * Creates a laboratory test from validated data and redirects to the catalogue.
     */
    public function store(SaveLabTestRequest $request, LabCatalogService $catalog)
    {
        $test = $catalog->create($request->validated(), $request->user());

        return redirect()->route('lab.tests.index')->with('success', "{$test->name} added to the catalog.");
    }

    /**
     * Authorizes the selected test and renders its edit form with the user's available options.
     */
    public function edit(Request $request, LabTest $labTest, LabCatalogService $catalog)
    {
        $catalog->authorizeTest($labTest, $request->user());

        return view('lab.tests.edit', ['test' => $labTest] + $catalog->formData($request->user(), $labTest));
    }

    /**
     * Updates the selected test from validated data and redirects to the catalogue.
     */
    public function update(SaveLabTestRequest $request, LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->update($labTest, $request->validated(), $request->user());

        return redirect()->route('lab.tests.index')->with('success', "{$labTest->name} updated.");
    }

    /**
     * Delegates test activation changes and returns with the resulting request-availability status.
     */
    public function toggle(LabTest $labTest, LabCatalogService $catalog)
    {
        $labTest = $catalog->toggle($labTest, request()->user());

        return back()->with('success', $labTest->name.($labTest->is_active ? ' activated.' : ' deactivated. Doctors can no longer request it.'));
    }
}
