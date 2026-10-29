<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoveMortuaryCaseRequest;
use App\Http\Requests\ReceiveMortuaryCaseRequest;
use App\Http\Requests\ReleaseMortuaryCaseRequest;
use App\Http\Requests\StoreExternalMortuaryCaseRequest;
use App\Http\Resources\Api\V1\MortuaryCaseResource;
use App\Models\HospitalSetting;
use App\Models\MortuaryCase;
use App\Services\MortuaryCaseService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MortuaryController extends Controller
{
    public function index(Request $request, MortuaryCaseService $mortuary): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:awaiting_transfer,received,released'],
        ]);
        $hospitalSettings = HospitalSetting::current();
        $cases = $mortuary->list($filters)->through(
            fn (MortuaryCase $case) => (new MortuaryCaseResource($case))->resolve($request),
        );

        return [
            'cases' => $cases,
            'fees' => [
                'intake' => (float) $hospitalSettings->mortuary_intake_fee,
                'daily_storage' => (float) $hospitalSettings->mortuary_daily_storage_fee,
                'state_tax_daily' => (float) $hospitalSettings->state_mortuary_tax_daily_fee,
            ],
        ];
    }

    public function storeWalkIn(StoreExternalMortuaryCaseRequest $request, MortuaryCaseService $mortuary): Response
    {
        $case = $mortuary->createWalkIn($request->validated(), $request->user());
        $case->load(['patient', 'openedBy', 'invoice.items']);

        return (new MortuaryCaseResource($case))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary): MortuaryCaseResource
    {
        return new MortuaryCaseResource($mortuary->details($mortuaryCase));
    }

    public function receive(ReceiveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary): MortuaryCaseResource
    {
        $case = $mortuary->receive($mortuaryCase, $request->validated(), $request->user());
        abort_if($case->status === 'awaiting_transfer', Response::HTTP_PAYMENT_REQUIRED, 'The intake fee must be paid before the body can be received into mortuary custody.');

        return new MortuaryCaseResource($mortuary->details($case));
    }

    public function move(MoveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary): MortuaryCaseResource
    {
        return new MortuaryCaseResource($mortuary->details(
            $mortuary->move($mortuaryCase, $request->validated(), $request->user()),
        ));
    }

    public function release(ReleaseMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary): MortuaryCaseResource
    {
        $case = $mortuary->release($mortuaryCase, $request->validated(), $request->user());
        abort_if($case->status === 'received', Response::HTTP_PAYMENT_REQUIRED, 'Pay all mortuary intake, storage, and State tax charges on the linked invoice before release.');

        return new MortuaryCaseResource($mortuary->details($case));
    }
}
