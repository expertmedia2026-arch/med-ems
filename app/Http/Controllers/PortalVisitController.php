<?php

namespace App\Http\Controllers;

use App\Models\LabAttachment;
use App\Models\Visit;
use App\Services\PortalVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalVisitController extends Controller
{
    public function index(Request $request, PortalVisitService $visits)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return view('portal.visits.index', ['visits' => $visits->index($patient)]);
    }

    public function show(Request $request, Visit $visit, PortalVisitService $visits)
    {
        return view('portal.visits.show', $visits->showForPatient($visit, $this->patientId($request)));
    }

    public function labReport(Request $request, Visit $visit, PortalVisitService $visits)
    {
        return view('lab.report', $visits->labReportData($visit, $this->patientId($request)));
    }

    public function attachment(Request $request, LabAttachment $attachment, PortalVisitService $visits)
    {
        abort_unless($visits->attachmentVisibleToPatient($attachment, $this->patientId($request)), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function patientId(Request $request): int
    {
        abort_unless($request->user()->patient_id, 403);

        return (int) $request->user()->patient_id;
    }
}
