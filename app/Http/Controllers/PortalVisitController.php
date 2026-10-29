<?php

namespace App\Http\Controllers;

use App\Models\LabAttachment;
use App\Models\Visit;
use App\Services\PortalVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serves patient-owned visit history, reports, and attachments through PortalVisitService.
 */
class PortalVisitController extends Controller
{
    /**
     * Requires a linked patient account and renders that patient's visit history.
     */
    public function index(Request $request, PortalVisitService $visits)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return view('portal.visits.index', ['visits' => $visits->index($patient)]);
    }

    /**
     * Loads a visit only within the signed-in patient's scope and renders its portal detail page.
     */
    public function show(Request $request, Visit $visit, PortalVisitService $visits)
    {
        return view('portal.visits.show', $visits->showForPatient($visit, $this->patientId($request)));
    }

    /**
     * Loads the patient's authorized laboratory report for a visit and renders the printable report.
     */
    public function labReport(Request $request, Visit $visit, PortalVisitService $visits)
    {
        return view('lab.report', $visits->labReportData($visit, $this->patientId($request)));
    }

    /**
     * Verifies patient visibility and file existence before returning a private laboratory attachment response.
     */
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

    /**
     * Resolves the authenticated patient's identifier or rejects users without a linked patient record.
     */
    private function patientId(Request $request): int
    {
        abort_unless($request->user()->patient_id, 403);

        return (int) $request->user()->patient_id;
    }
}
