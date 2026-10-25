<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'medication_id' => ['sometimes', 'integer', 'min:1'],
            'lot_id' => ['sometimes', 'integer', 'min:1'],
            'type' => ['sometimes', 'string', 'in:DISPENSATION,TRANSFER_OUT,TRANSFER_IN,ADJUSTMENT,RECEIPT'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryMovement::query()->with([
            'inventory.warehouse:id,code,name',
            'inventory.lot:id,medication_id,lot_number,expiration_date',
            'inventory.lot.medication:id,code,name',
            'user:id,name',
        ]);

        if (isset($filters['warehouse_id'])) {
            $query->whereHas('inventory', fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']));
        }
        if (isset($filters['medication_id'])) {
            $query->whereHas('inventory.lot', fn ($q) => $q->where('medication_id', $filters['medication_id']));
        }
        if (isset($filters['lot_id'])) {
            $query->whereHas('inventory', fn ($q) => $q->where('lot_id', $filters['lot_id']));
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        return response()->json($query->latest('occurred_at')->paginate($filters['per_page'] ?? 25));
    }
}
