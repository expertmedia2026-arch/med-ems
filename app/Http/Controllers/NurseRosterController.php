<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNurseRosterRequest;
use App\Models\User;
use App\Services\NurseRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Validates nurse roster filters and delegates schedule retrieval and saving to NurseRosterService.
 */
class NurseRosterController extends Controller
{
    /**
     * Validates week and list filters, normalizes the week start, then renders the scoped nurse roster.
     */
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

    /**
     * Saves the validated roster for the selected staff member and returns to the same filtered week.
     */
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
