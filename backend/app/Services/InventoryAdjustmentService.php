<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function adjust(int $inventoryId, int $targetQuantity, string $reason, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($inventoryId, $targetQuantity, $reason, $userId) {
            $inventory = Inventory::whereKey($inventoryId)->lockForUpdate()->firstOrFail();
            $before = (int) $inventory->quantity;
            $delta = $targetQuantity - $before;
            if ($delta === 0) {
                throw ValidationException::withMessages(['quantity' => 'La existencia ya tiene esa cantidad; no se generó un movimiento.']);
            }
            $inventory->quantity = $targetQuantity;
            $inventory->save();

            return InventoryMovement::create([
                'inventory_id' => $inventory->id, 'user_id' => $userId,
                'type' => 'ADJUSTMENT', 'quantity' => $delta,
                'balance_before' => $before, 'balance_after' => $targetQuantity,
                'reference_type' => 'INVENTORY_ADJUSTMENT', 'reference_id' => $inventory->id,
                'observations' => trim($reason), 'occurred_at' => now(),
            ]);
        }, 3);
    }
}
