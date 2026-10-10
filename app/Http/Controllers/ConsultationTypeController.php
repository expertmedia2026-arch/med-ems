<?php

namespace App\Http\Controllers;

use App\Models\ConsultationType;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsultationTypeController extends Controller
{
    public function index()
    {
        $consultationTypes = ConsultationType::with('department')
            ->orderBy('department_id')
            ->orderBy('name')
            ->paginate(15);

        return view('consultation-types.index', compact('consultationTypes'));
    }

    public function create()
    {
        return view('consultation-types.create', [
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        ConsultationType::create($data);

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type added.');
    }

    public function edit(ConsultationType $consultationType)
    {
        return view('consultation-types.edit', [
            'consultationType' => $consultationType,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ConsultationType $consultationType)
    {
        $data = $this->validated($request, $consultationType);
        $data['is_active'] = $request->boolean('is_active');

        $consultationType->update($data);

        return redirect()->route('consultation-types.index')->with('success', 'Consultation type updated.');
    }

    private function validated(Request $request, ?ConsultationType $consultationType = null): array
    {
        return $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('consultation_types', 'name')
                    ->where('department_id', $request->input('department_id'))
                    ->ignore($consultationType?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
