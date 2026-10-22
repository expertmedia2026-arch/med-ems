<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNurseRosterRequest;
use App\Models\User;
use App\Services\NurseRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NurseRosterController extends Controller
{
    public function index(Request $request, NurseRosterService $roster)
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $weekStart = Carbon::parse($validated['week'] ?? now()->toDateString())
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        return view('nursing.roster.index', $roster->indexData($request->user(), $weekStart, $request->only(['department', 'search'])));
    }

    public function update(StoreNurseRosterRequest $request, User $staff, NurseRosterService $roster)
    {
        $validated = $request->validated();
        $roster->saveWeek($staff, $validated, $request->user());

        return redirect()->route('nursing.roster.index', array_merge(
            ['week' => $validated['week_start']],
            $request->only(['department', 'search', 'page']),
        ))
            ->with('success', "Roster saved for {$staff->name}.");
    }
}
