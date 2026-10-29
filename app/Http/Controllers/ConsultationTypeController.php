<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConsultationTypeRequest;
use App\Models\ConsultationType;
use App\Services\ConsultationTypeService;

/**
 * Presents and updates the consultation-type catalogue through ConsultationTypeService.
 */
class ConsultationTypeController extends Controller
{
    /**
     * Retrieves consultation types through the catalogue service and renders the management list.
     */
    public function index(ConsultationTypeService $types)
    {
        $consultationTypes = $types->index();

        return view('consultation-types.index', compact('consultationTypes'));
    }

    /**
     * Loads the department choices needed to render a new consultation-type form.
     */
    public function create(ConsultationTypeService $types)
    {
        return view('consultation-types.create', [
            'departments' => $types->departments(),
        ]);
    }

    /**
     * Submits validated catalogue fields to the service and redirects to the updated consultation-type list.
     */
    public function store(SaveConsultationTypeRequest $request, ConsultationTypeService $types)
    {
        $types->create($request->validated());

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type added.');
    }

    /**
     * Renders the selected consultation type for editing with the current department choices.
     */
    public function edit(ConsultationType $consultationType, ConsultationTypeService $types)
    {
        return view('consultation-types.edit', [
            'consultationType' => $consultationType,
            'departments' => $types->departments(),
        ]);
    }

    /**
     * Validates and delegates catalogue changes, then redirects to the consultation-type list.
     */
    public function update(SaveConsultationTypeRequest $request, ConsultationType $consultationType, ConsultationTypeService $types)
    {
        $types->update($consultationType, $request->validated());

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type updated.');
    }
}
