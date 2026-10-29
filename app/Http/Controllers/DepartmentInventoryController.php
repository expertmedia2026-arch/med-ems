<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordDepartmentInventoryMovementRequest;
use App\Http\Requests\SaveDepartmentInventoryItemRequest;
use App\Models\DepartmentInventoryItem;
use App\Services\DepartmentInventoryService;
use Illuminate\Http\Request;

/**
 * Enforces department inventory access and delegates stock operations to DepartmentInventoryService.
 */
class DepartmentInventoryController extends Controller
{
    /**
     * Restricts the inventory page to administrators or assigned lab staff and renders their scoped stock list.
     */
    public function index(Request $request, DepartmentInventoryService $inventory)
    {
        abort_unless($request->user()->hasRole('admin', 'lab'), 403);
        abort_if($request->user()->hasRole('lab') && ! $request->user()->department_id, 403, 'Assign this staff member to a department first.');

        return view('lab.inventory.index', $inventory->indexData($request->user(), $request->integer('department_id') ?: null));
    }

    /**
     * Creates an inventory item from validated input and redirects to the department inventory.
     */
    public function store(SaveDepartmentInventoryItemRequest $request, DepartmentInventoryService $inventory)
    {
        $item = $inventory->create($request->validated(), $request->user());

        return redirect()->route('lab.inventory.index')->with('success', "{$item->name} added to the department inventory.");
    }

    /**
     * Records a validated stock receipt against the selected item and returns with the updated balance.
     */
    public function restock(RecordDepartmentInventoryMovementRequest $request, DepartmentInventoryItem $item, DepartmentInventoryService $inventory)
    {
        $item = $inventory->recordMovement($item, $request->validated(), $request->user(), true);

        return back()->with('success', "Stock received for {$item->name}. Balance: {$item->quantity_on_hand} {$item->unit}.");
    }

    /**
     * Records a validated stock consumption against the selected item and returns with the updated balance.
     */
    public function consume(RecordDepartmentInventoryMovementRequest $request, DepartmentInventoryItem $item, DepartmentInventoryService $inventory)
    {
        $item = $inventory->recordMovement($item, $request->validated(), $request->user(), false);

        return back()->with('success', "Usage recorded for {$item->name}. Balance: {$item->quantity_on_hand} {$item->unit}.");
    }
}
