<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\DepartmentInventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DepartmentInventoryService
{
    public function indexData(User $user, ?int $departmentId = null): array
    {
        abort_if($user->hasRole(Role::Lab) && ! $user->department_id, 403, 'Assign this staff member to a department first.');

        $query = DepartmentInventoryItem::query()->with([
            'department:id,name',
            'movements' => fn ($movements) => $movements->with('recorder:id,name')->latest()->limit(5),
        ])
            ->when($user->hasRole(Role::Lab), fn ($query) => $query->where('department_id', $user->department_id))
            ->when($user->hasRole(Role::Admin) && $departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('department_id')->orderBy('name');

        return [
            'items' => $query->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'selectedDepartmentId' => $user->hasRole(Role::Lab) ? $user->department_id : $departmentId,
        ];
    }

    public function create(array $data, User $user): DepartmentInventoryItem
    {
        $this->authorizeDepartment((int) $data['department_id'], $user);

        return DB::transaction(function () use ($data, $user) {
            $opening = (float) ($data['opening_quantity'] ?? 0);
            $item = DepartmentInventoryItem::create([
                'department_id' => $data['department_id'],
                'name' => trim($data['name']),
                'category' => $data['category'] ?? null,
                'unit' => trim($data['unit']),
                'quantity_on_hand' => $opening,
                'reorder_level' => $data['reorder_level'],
            ]);

            if ($opening > 0) {
                $item->movements()->create([
                    'recorded_by' => $user->id,
                    'movement_type' => 'opening',
                    'quantity' => $opening,
                    'balance_after' => $opening,
                    'note' => 'Opening stock balance',
                ]);
            }

            return $item;
        });
    }

    public function recordMovement(DepartmentInventoryItem $item, array $data, User $user, bool $restock): DepartmentInventoryItem
    {
        $this->authorizeDepartment((int) $item->department_id, $user);

        return DB::transaction(function () use ($item, $data, $user, $restock) {
            $item = DepartmentInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $quantity = (float) $data['quantity'];
            $balance = (float) $item->quantity_on_hand + ($restock ? $quantity : -$quantity);
            abort_if($balance < 0, 422, 'There is not enough stock to record this usage.');

            $item->update(['quantity_on_hand' => $balance]);
            $item->movements()->create([
                'recorded_by' => $user->id,
                'movement_type' => $restock ? 'restock' : 'usage',
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            return $item->refresh();
        });
    }

    private function authorizeDepartment(int $departmentId, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin) || ($user->hasRole(Role::Lab) && (int) $user->department_id === $departmentId), 403);
    }
}
