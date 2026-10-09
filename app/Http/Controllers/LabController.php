<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\WorkStatus;
use App\Models\LabRequest;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Doctors only see requests they made themselves
        $base = LabRequest::with(['visit.patient', 'test', 'requester'])
            ->when($user->hasRole(Role::Doctor), fn ($q) => $q->where('requested_by', $user->id));

        return view('lab.index', [
            'pending'    => (clone $base)->pending()->with('invoiceItem')->oldest()->get(),
            'completed'  => (clone $base)->completed()->with('processor')->withCount('attachments')->latest('completed_at')->limit(20)->get(),
            'canProcess' => $user->hasRole(Role::Admin, Role::Lab),
        ]);
    }

    public function show(Request $request, LabRequest $labRequest)
    {
        $user = $request->user();

        abort_if(
            $user->hasRole(Role::Doctor) && $labRequest->requested_by !== $user->id,
            403,
            'You can only view results for tests you requested.'
        );

        $labRequest->load(['visit.patient', 'test', 'requester', 'processor', 'attachments', 'invoiceItem']);

        return view('lab.show', [
            'labRequest' => $labRequest,
            'canProcess' => $user->hasRole(Role::Admin, Role::Lab),
        ]);
    }

    public function update(Request $request, LabRequest $labRequest)
    {
        $labRequest->loadMissing('invoiceItem');

        if (! $labRequest->is_paid) {
            return redirect()
                ->route('lab.show', $labRequest)
                ->with('error', 'Payment is required before a result can be entered.');
        }

        $data = $request->validate([
            'result'  => ['nullable', 'string', 'max:5000'],
            'files'   => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'files.*.mimes'    => 'Only JPG, PNG, WebP or PDF files are allowed.',
            'files.*.max'      => 'Each file must be 10 MB or smaller.',
            'files.*.uploaded' => 'Upload failed. The file may be larger than the server allows.',
        ]);

        // A result needs text, at least one file, or both
        if (blank($data['result'] ?? null) && ! $request->hasFile('files') && $labRequest->attachments()->doesntExist()) {
            return back()->withInput()->withErrors(['result' => 'Enter a result or attach at least one file.']);
        }

        foreach ($request->file('files', []) as $file) {
            $labRequest->attachments()->create([
                'path'          => $file->store("lab-attachments/{$labRequest->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()->id,
            ]);
        }

        $labRequest->update([
            'result'       => $data['result'] ?? null,
            'status'       => WorkStatus::Completed,
            'processed_by' => $request->user()->id,
            'completed_at' => $labRequest->completed_at ?? now(),
        ]);

        return redirect()
            ->route('lab.index')
            ->with('success', "Result saved for {$labRequest->visit->patient->full_name}.");
    }
}