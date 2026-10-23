<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveMortuaryCaseRequest;
use App\Http\Requests\ReceiveMortuaryCaseRequest;
use App\Http\Requests\ReleaseMortuaryCaseRequest;
use App\Http\Requests\StoreExternalMortuaryCaseRequest;
use App\Models\MortuaryCase;
use App\Services\MortuaryCaseService;
use Illuminate\Http\Request;

class MortuaryController extends Controller
{
    public function index(Request $request, MortuaryCaseService $mortuary)
    {
        abort_unless($request->user()->canAccessMortuary(), 403);

        return view('mortuary.index', [
            'cases' => $mortuary->list($request->only('q', 'status')),
            'filters' => $request->only('q', 'status'),
            'intakeFee' => \App\Models\HospitalSetting::current()->mortuary_intake_fee,
            'dailyStorageFee' => \App\Models\HospitalSetting::current()->mortuary_daily_storage_fee,
            'stateTaxDailyFee' => \App\Models\HospitalSetting::current()->state_mortuary_tax_daily_fee,
        ]);
    }

    public function storeWalkIn(StoreExternalMortuaryCaseRequest $request, MortuaryCaseService $mortuary)
    {
        $case = $mortuary->createWalkIn($request->validated(), $request->user());

        return redirect()->route('mortuary.show', $case)->with('success', 'Walk-in mortuary case opened and invoice created.');
    }

    public function show(Request $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        abort_unless($request->user()->canAccessMortuary(), 403);

        $case = $mortuary->details($mortuaryCase);
        $mortuaryItems = $case->invoice?->items->where('category', 'mortuary')
            ->filter(fn ($item) => $item->billable_type === $case->getMorphClass() && (int) $item->billable_id === (int) $case->id)
            ?? collect();
        $intakeFeePaid = (float) $case->intake_fee <= 0 || (bool) $mortuaryItems
            ->firstWhere('description', 'Mortuary intake fee')?->is_paid;
        $mortuaryChargesPaid = $mortuaryItems->every(fn ($item) => $item->is_paid);

        return view('mortuary.show', compact('case', 'intakeFeePaid', 'mortuaryChargesPaid'));
    }

    public function receive(ReceiveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        $case = $mortuary->receive($mortuaryCase, $request->validated(), $request->user());

        if ($case->status === 'awaiting_transfer') {
            return redirect()->route('mortuary.show', $case)->with('error', 'The intake fee must be paid before the body can be received into mortuary custody. The charge is on the linked invoice.');
        }

        return redirect()->route('mortuary.show', $case)->with('success', 'Mortuary receipt recorded and custody started.');
    }

    public function move(MoveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        $case = $mortuary->move($mortuaryCase, $request->validated(), $request->user());

        return redirect()->route('mortuary.show', $case)->with('success', 'Storage location updated and added to the custody history.');
    }

    public function release(ReleaseMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        $case = $mortuary->release($mortuaryCase, $request->validated(), $request->user());

        if ($case->status === 'received') {
            return redirect()->route('mortuary.show', $case)->with('error', 'Pay all mortuary intake, storage, and State tax charges on the linked invoice before release.');
        }

        return redirect()->route('mortuary.show', $case)->with('success', 'Release details recorded and the mortuary case closed.');
    }
}
