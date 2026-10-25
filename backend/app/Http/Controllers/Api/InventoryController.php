<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'medication_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:all,available,low_stock,expired,expiring'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Inventory::query()
            ->with([
                'warehouse:id,code,name',
                'lot:id,medication_id,lot_number,manufacturing_date,expiration_date,is_active',
                'lot.medication:id,code,name,active_ingredient,requires_prescription,is_controlled',
            ]);

        if (isset($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (isset($filters['medication_id'])) {
            $query->whereHas('lot', function ($lotQuery) use ($filters) {
                $lotQuery->where('medication_id', $filters['medication_id']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->whereHas('lot.medication', function ($medicationQuery) use ($search) {
                $medicationQuery->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%")
                        ->orWhere('active_ingredient', 'ilike', "%{$search}%");
                });
            });
        }

        $status = $filters['status'] ?? 'all';
        $today = now()->toDateString();
        $expiryLimit = now()->addDays(90)->toDateString();

        if ($status === 'available') {
            $query->where('quantity', '>', 0)
                ->whereHas('lot', function ($lotQuery) use ($today) {
                    $lotQuery->where('expiration_date', '>=', $today)
                        ->where('is_active', true);
                });
        }

        if ($status === 'low_stock') {
            $query->whereColumn('quantity', '<=', 'minimum_stock');
        }

        if ($status === 'expired') {
            $query->whereHas('lot', function ($lotQuery) use ($today) {
                $lotQuery->where('expiration_date', '<', $today);
            });
        }

        if ($status === 'expiring') {
            $query->whereHas('lot', function ($lotQuery) use ($today, $expiryLimit) {
                $lotQuery->whereBetween('expiration_date', [$today, $expiryLimit])
                    ->where('is_active', true);
            });
        }

        $inventory = $query
            ->orderBy('warehouse_id')
            ->orderBy('lot_id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json($inventory);
    }
}
