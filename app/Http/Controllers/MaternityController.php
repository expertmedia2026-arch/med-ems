<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordMaternityDeliveryRequest;
use App\Http\Requests\StartMaternityLaborRequest;
use App\Http\Requests\UpdateMaternityPostpartumRequest;
use App\Models\Admission;
use App\Models\MaternityEpisode;
use App\Services\MaternityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coordinates maternity episode pages and workflow actions with MaternityService.
 */
class MaternityController extends Controller
{
    /** Render the tenant-scoped maternity episode list. */
    public function index(Request $request, MaternityService $maternity): View
    {
        return view('maternity.index', [
            'episodes' => $maternity->indexData($request->user()),
        ]);
    }

    /** Create the labor episode and return staff to its workflow page. */
    public function start(StartMaternityLaborRequest $request, Admission $admission, MaternityService $maternity): RedirectResponse
    {
        $episode = $maternity->startLabor($admission, $request->validated(), $request->user());

        return redirect()->route('maternity.episodes.show', $episode)
            ->with('success', 'Maternity labor episode started.');
    }

    /** Render the episode together with the hospital's current delivery fee schedule. */
    public function show(Request $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): View
    {
        return view('maternity.show', [
            'episode' => $maternity->show($maternityEpisode, $request->user()),
            'deliveryPricing' => $maternity->deliveryPricing(),
        ]);
    }

    /** Persist delivery, newborns, and any applicable invoice items as one workflow action. */
    public function deliver(RecordMaternityDeliveryRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): RedirectResponse
    {
        $maternity->recordDelivery($maternityEpisode, $request->validated(), $request->user());

        return redirect()->route('maternity.episodes.show', $maternityEpisode)
            ->with('success', 'Delivery and newborn records saved. Any configured delivery and supply-pack fees were added to Billing.');
    }

    /** Save postpartum notes and return to the episode summary. */
    public function postpartum(UpdateMaternityPostpartumRequest $request, MaternityEpisode $maternityEpisode, MaternityService $maternity): RedirectResponse
    {
        $maternity->updatePostpartum($maternityEpisode, $request->validated('postpartum_notes'), $request->user());

        return redirect()->route('maternity.episodes.show', $maternityEpisode)
            ->with('success', 'Postpartum notes saved.');
    }
}
