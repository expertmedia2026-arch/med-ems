<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordMaternityDeliveryRequest;
use App\Http\Requests\StartMaternityLaborRequest;
use App\Http\Requests\UpdateMaternityPostpartumRequest;
use App\Http\Resources\Api\V1\MaternityEpisodeResource;
use App\Models\Admission;
use App\Models\MaternityEpisode;
use App\Services\MaternityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MaternityController extends Controller
{
    public function fees(MaternityService $maternity): array
    {
        return [
            'currency' => 'NGN',
            'delivery_fees' => $maternity->deliveryPricing(),
        ];
    }

    public function index(Request $request, MaternityService $maternity): AnonymousResourceCollection
    {
        return MaternityEpisodeResource::collection($maternity->indexData($request->user()));
    }

    public function start(StartMaternityLaborRequest $request, Admission $admission, MaternityService $maternity): Response
    {
        $episode = $maternity->startLabor($admission, $request->validated(), $request->user());

        return (new MaternityEpisodeResource($maternity->show($episode, $request->user())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource($maternity->show($maternityEpisode, $request->user()));
    }

    public function deliver(RecordMaternityDeliveryRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource(
            $maternity->recordDelivery($maternityEpisode, $request->validated(), $request->user()),
        );
    }

    public function postpartum(UpdateMaternityPostpartumRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource(
            $maternity->updatePostpartum($maternityEpisode, $request->validated('postpartum_notes'), $request->user()),
        );
    }
}
