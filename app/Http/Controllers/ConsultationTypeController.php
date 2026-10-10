<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConsultationTypeRequest;
use App\Models\ConsultationType;
use App\Services\ConsultationTypeService;

class ConsultationTypeController extends Controller
{
    public function index(ConsultationTypeService $types)
    {
        $consultationTypes = $types->index();

        return view('consultation-types.index', compact('consultationTypes'));
    }

    public function create(ConsultationTypeService $types)
    {
        return view('consultation-types.create', [
            'departments' => $types->departments(),
        ]);
    }

    public function store(SaveConsultationTypeRequest $request, ConsultationTypeService $types)
    {
        $types->create($request->validated());

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type added.');
    }

    public function edit(ConsultationType $consultationType, ConsultationTypeService $types)
    {
        return view('consultation-types.edit', [
            'consultationType' => $consultationType,
            'departments' => $types->departments(),
        ]);
    }

    public function update(SaveConsultationTypeRequest $request, ConsultationType $consultationType, ConsultationTypeService $types)
    {
        $types->update($consultationType, $request->validated());

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type updated.');
    }
}
