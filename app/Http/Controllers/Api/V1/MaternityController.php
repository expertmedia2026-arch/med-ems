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

/**
 * Provides maternity episode and delivery-pricing APIs through MaternityService.
 */
class MaternityController extends Controller
{
    /**
     * Expose the authenticated tenant's delivery and supply-pack prices to API clients.
     * Load pricing through MaternityService and return NGN currency with the delivery fee schedule.
     */
    public function fees(MaternityService $maternity): array
    {
        return [
            'currency' => 'NGN',
            'delivery_fees' => $maternity->deliveryPricing(),
        ];
    }

    /**
     * Return the caller's permitted maternity episodes using the shared API resource shape.
     * Delegate visibility and listing to MaternityService and return MaternityEpisodeResource items.
     */
    public function index(Request $request, MaternityService $maternity): AnonymousResourceCollection
    {
        return MaternityEpisodeResource::collection($maternity->indexData($request->user()));
    }

    /**
     * Start labor for an active admission and respond with the created episode.
     * Pass validated labor details and the authenticated user to MaternityService; return a MaternityEpisodeResource with HTTP 201.
     */
    public function start(StartMaternityLaborRequest $request, Admission $admission, MaternityService $maternity): Response
    {
        $episode = $maternity->startLabor($admission, $request->validated(), $request->user());

        return (new MaternityEpisodeResource($maternity->show($episode, $request->user())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Return one episode after the shared service confirms ward-level access.
     * Delegate visibility and detail loading to MaternityService and return a MaternityEpisodeResource.
     */
    public function show(Request $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource($maternity->show($maternityEpisode, $request->user()));
    }

    /**
     * Save delivery and newborn records and include the updated episode in the response.
     * Pass validated delivery details and the authenticated user to MaternityService; return the updated MaternityEpisodeResource.
     */
    public function deliver(RecordMaternityDeliveryRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource(
            $maternity->recordDelivery($maternityEpisode, $request->validated(), $request->user()),
        );
    }

    /**
     * Record the postpartum summary and return the updated maternity resource.
     * Pass validated postpartum notes and the authenticated user to MaternityService; return the updated MaternityEpisodeResource.
     */
    public function postpartum(UpdateMaternityPostpartumRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): MaternityEpisodeResource
    {
        return new MaternityEpisodeResource(
            $maternity->updatePostpartum($maternityEpisode, $request->validated('postpartum_notes'), $request->user()),
        );
    }
}
