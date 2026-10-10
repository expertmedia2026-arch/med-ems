<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabReferralFormRequest;
use App\Http\Requests\LabSearchRequest;
use App\Http\Requests\LabTestIndexRequest;
use App\Http\Requests\SaveLabResultRequest;
use App\Http\Requests\SaveLabTestRequest;
use App\Http\Requests\StoreLabReferralRequest;
use App\Http\Resources\Api\V1\LabRequestResource;
use App\Http\Resources\Api\V1\LabTestResource;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\LabAttachment;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\Visit;
use App\Services\LabAttachmentService;
use App\Services\LabCatalogService;
use App\Services\LabReferralService;
use App\Services\LabWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabOperationsController extends Controller
{
    public function index(LabSearchRequest $request, LabWorkflowService $lab): array
    {
        $data = $lab->indexData($request->user(), trim($request->validated('q') ?? ''));

        return [
            'pending' => LabRequestResource::collection($data['pending']),
            'completed' => LabRequestResource::collection($data['completed']),
        ];
    }

    public function show(Request $request, LabRequest $labRequest, LabWorkflowService $lab): LabRequestResource
    {
        $data = $lab->detailData($labRequest, $request->user());

        return new LabRequestResource($data['labRequest']);
    }

    public function update(SaveLabResultRequest $request, LabRequest $labRequest, LabWorkflowService $lab): LabRequestResource
    {
        $outcome = $lab->saveResult($labRequest, $request->validated(), $request->file('files', []), $request->user());
        abort_if($outcome === 'locked', 409, 'This result is locked because the visit is completed.');
        abort_if($outcome === 'unpaid', 402, 'Payment is required before a result can be entered.');

        return new LabRequestResource($labRequest->refresh()->load(['test', 'visit.patient', 'attachments']));
    }

    public function report(Request $request, Visit $visit, LabWorkflowService $lab): array
    {
        $data = $lab->reportData($visit, $request->user());

        return ['visit' => new VisitResource($data['visit']), 'results' => LabRequestResource::collection($data['requests'])];
    }

    public function showAttachment(Request $request, LabAttachment $attachment, LabAttachmentService $attachments)
    {
        abort_unless($attachments->canView($request->user(), $attachment), 403);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function deleteAttachment(LabAttachment $attachment, LabAttachmentService $attachments)
    {
        $attachments->delete($attachment);

        return response()->noContent();
    }

    public function tests(LabTestIndexRequest $request, LabCatalogService $catalog)
    {
        $filters = $request->validated();
        if ($request->user()->hasRole(Role::Doctor)) {
            $filters['status'] = 'active';
        }

        return LabTestResource::collection($catalog->indexData($filters)['tests']);
    }

    public function createTest(SaveLabTestRequest $request, LabCatalogService $catalog): LabTestResource
    {
        return new LabTestResource($catalog->create($request->validated()));
    }

    public function updateTest(SaveLabTestRequest $request, LabTest $labTest, LabCatalogService $catalog): LabTestResource
    {
        return new LabTestResource($catalog->update($labTest, $request->validated()));
    }

    public function toggleTest(LabTest $labTest, LabCatalogService $catalog): LabTestResource
    {
        return new LabTestResource($catalog->toggle($labTest));
    }

    public function createReferralForm(LabReferralFormRequest $request, LabReferralService $referrals): array
    {
        $data = $referrals->createFormData($request->validated('patient'));

        return [
            'patients' => $data['patients']->map(fn ($patient) => ['id' => $patient->id, 'hospital_number' => $patient->hospital_number, 'name' => $patient->full_name]),
            'tests' => LabTestResource::collection($data['tests']),
            'selected_patient' => $data['selected_patient'] ? [
                'id' => $data['selected_patient']->id,
                'hospital_number' => $data['selected_patient']->hospital_number,
                'name' => $data['selected_patient']->full_name,
            ] : null,
        ];
    }

    public function createReferral(StoreLabReferralRequest $request, LabReferralService $referrals): array
    {
        $result = $referrals->create($request->validated(), $request->user()->id, $request->boolean('confirm_new'));
        if ($result['matches']->isNotEmpty()) {
            return ['confirmation_required' => true, 'possible_matches' => $result['matches']->map(fn ($patient) => [
                'id' => $patient->id,
                'hospital_number' => $patient->hospital_number,
                'name' => $patient->full_name,
            ])->values()];
        }

        return [
            'confirmation_required' => false,
            'visit' => new VisitResource($result['visit']->load(['patient', 'labRequests.test'])),
            'total' => $result['total'],
            'new_patient' => $result['is_new'],
        ];
    }

    public function cancelReferral(Visit $visit, LabReferralService $referrals)
    {
        abort_unless($referrals->cancel($visit), 409, 'This request cannot be cancelled after payment has been taken or a result entered.');

        return response()->noContent();
    }
}
