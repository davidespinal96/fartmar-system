<?php

namespace App\Services;

use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensationService
{
    /**
     * Consulta una fórmula y sus medicamentos pendientes de dispensar.
     */
    public function getPrescriptionForDispensation(
        int $prescriptionId
    ): Prescription {
        $prescription = Prescription::with([
            'patient',
            'items.medication',
        ])->find($prescriptionId);

        if (! $prescription) {
            throw ValidationException::withMessages([
                'prescription_id' => 'La fórmula médica no existe.',
            ]);
        }

        if ($prescription->status !== 'ACTIVA') {
            throw ValidationException::withMessages([
                'prescription_id' => 'La fórmula médica no está activa.',
            ]);
        }

        if (
            $prescription->valid_until !== null
            && today()->gt($prescription->valid_until)
        ) {
            throw ValidationException::withMessages([
                'prescription_id' => 'La fórmula médica está vencida.',
            ]);
        }

        return $prescription;
    }

    /**
     * Consulta los lotes disponibles siguiendo FEFO:
     * primero se utilizan los lotes que vencen antes.
     */
    public function getAvailableLots(
        int $medicationId,
        int $warehouseId
    ) {
        return Inventory::query()
            ->with(['lot.medication', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->whereHas('lot', function ($query) use ($medicationId) {
                $query
                    ->where('medication_id', $medicationId)
                    ->where('is_active', true)
                    ->whereDate('expiration_date', '>=', today());
            })
            ->orderBy(
                Inventory::query()
                    ->select('expiration_date')
                    ->from('lots')
                    ->whereColumn('lots.id', 'inventories.lot_id')
                    ->limit(1)
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * Registra una dispensación completa dentro de una transacción.
     *
     * Cada medicamento puede consumirse de varios lotes,
     * respetando el orden FEFO.
     */
    public function dispense(
        array $data,
        int $userId
    ): Dispensation {
        return DB::transaction(function () use ($data, $userId) {

            // Evita procesar dos veces la misma operación.
            $existing = Dispensation::where(
                'idempotency_key',
                $data['idempotency_key']
            )->first();

            if ($existing) {
                $existingItemRows = $existing->items()->get();

                $existingItems = $existingItemRows
                    ->groupBy('prescription_item_id')
                    ->map(fn ($items, $id) => [
                        'prescription_item_id' => (int) $id,
                        'quantity' => (int) $items->sum('quantity'),
                    ])
                    ->sortBy('prescription_item_id')
                    ->values()
                    ->all();

                $requestedItems = collect($data['items'])
                    ->map(fn ($item) => [
                        'prescription_item_id' => (int) $item['prescription_item_id'],
                        'quantity' => (int) $item['quantity'],
                    ])
                    ->sortBy('prescription_item_id')
                    ->values()
                    ->all();

                $inventoryIds = $existingItemRows
                    ->pluck('inventory_id')
                    ->unique()
                    ->values();

                $warehouseIds = Inventory::whereIn('id', $inventoryIds)
                    ->pluck('warehouse_id')
                    ->unique();

                $sameWarehouse = $warehouseIds->count() === 1
                    && (int) $warehouseIds->first() === (int) $data['warehouse_id'];

                $sameObservations = ($existing->observations ?? null)
                    === ($data['observations'] ?? null);

                $sameRequest =
                    (int) $existing->prescription_id === (int) $data['prescription_id']
                    && $sameWarehouse
                    && $sameObservations
                    && $existingItems === $requestedItems;

                if (! $sameRequest) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'La clave de idempotencia ya fue utilizada con datos diferentes.',
                        ], 409)
                    );
                }

                return $existing->load('items.inventory.lot.medication');
            }

            // Bloquea la fórmula durante la operación.
            $prescription = Prescription::query()
                ->whereKey($data['prescription_id'])
                ->lockForUpdate()
                ->first();

            if (! $prescription) {
                throw ValidationException::withMessages([
                    'prescription_id' => 'La fórmula médica no existe.',
                ]);
            }

            if ($prescription->status !== 'ACTIVA') {
                throw ValidationException::withMessages([
                    'prescription_id' => 'La fórmula médica no está activa.',
                ]);
            }

            if (
                $prescription->valid_until !== null
                && today()->gt($prescription->valid_until)
            ) {
                throw ValidationException::withMessages([
                    'prescription_id' => 'La fórmula médica está vencida.',
                ]);
            }

            $dispensation = Dispensation::create([
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'dispensed_by' => $userId,
                'idempotency_key' => $data['idempotency_key'],
                'status' => 'COMPLETED',
                'dispensed_at' => now(),
                'observations' => $data['observations'] ?? null,
            ]);

            foreach ($data['items'] as $requestedItem) {

                // Bloquea la línea de la fórmula para impedir
                // dispensaciones concurrentes de la misma cantidad.
                $prescriptionItem = PrescriptionItem::query()
                    ->whereKey($requestedItem['prescription_item_id'])
                    ->where('prescription_id', $prescription->id)
                    ->lockForUpdate()
                    ->first();

                if (! $prescriptionItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los medicamentos no pertenece a la fórmula indicada.',
                    ]);
                }

                $quantityToDispense = (int) $requestedItem['quantity'];

                $controlledAuthorization = null;
                if ($prescriptionItem->medication()->value('is_controlled')) {
                    $authorizationId = $requestedItem['controlled_authorization_id'] ?? null;
                    if (! $authorizationId) {
                        throw ValidationException::withMessages([
                            'items' => 'Este medicamento de control especial requiere autorización vigente de un regente.',
                        ]);
                    }
                    $controlledAuthorization = DB::table('controlled_medication_authorizations')
                        ->where('id', $authorizationId)->lockForUpdate()->first();
                    if (! $controlledAuthorization
                        || $controlledAuthorization->status !== 'AUTHORIZED'
                        || (int) $controlledAuthorization->prescription_item_id !== (int) $prescriptionItem->id
                        || (int) $controlledAuthorization->warehouse_id !== (int) $data['warehouse_id']
                        || (int) $controlledAuthorization->quantity !== $quantityToDispense
                        || (int) $controlledAuthorization->authorized_by === $userId) {
                        throw ValidationException::withMessages([
                            'items' => 'La autorización no coincide con la fórmula, bodega o cantidad, ya fue usada o la aprobó el mismo usuario.',
                        ]);
                    }
                }

                $remaining = $prescriptionItem->remainingQuantity();

                if ($quantityToDispense > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "La cantidad solicitada supera lo pendiente para el medicamento ID {$prescriptionItem->medication_id}.",
                    ]);
                }

                $remainingToTake = $quantityToDispense;

                // Selecciona y bloquea los inventarios válidos
                // en orden FEFO.
                $inventories = Inventory::query()
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->where('quantity', '>', 0)
                    ->whereHas('lot', function ($query) use ($prescriptionItem) {
                        $query
                            ->where('medication_id', $prescriptionItem->medication_id)
                            ->where('is_active', true)
                            ->whereDate('expiration_date', '>=', today());
                    })
                    ->orderBy(
                        Inventory::query()
                            ->select('expiration_date')
                            ->from('lots')
                            ->whereColumn('lots.id', 'inventories.lot_id')
                            ->limit(1)
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = (int) $inventories->sum('quantity');

                if ($available < $quantityToDispense) {
                    throw ValidationException::withMessages([
                        'items' => "Existencias insuficientes para el medicamento ID {$prescriptionItem->medication_id}. Disponibles: {$available}.",
                    ]);
                }

                foreach ($inventories as $inventory) {
                    if ($remainingToTake <= 0) {
                        break;
                    }

                    $take = min(
                        $remainingToTake,
                        (int) $inventory->quantity
                    );

                    $before = (int) $inventory->quantity;
                    $after = $before - $take;

                    $inventory->quantity = $after;
                    $inventory->save();

                    DispensationItem::create([
                        'dispensation_id' => $dispensation->id,
                        'prescription_item_id' => $prescriptionItem->id,
                        'inventory_id' => $inventory->id,
                        'quantity' => $take,
                        'controlled_authorization_id' => $controlledAuthorization?->id,
                    ]);

                    InventoryMovement::create([
                        'inventory_id' => $inventory->id,
                        'user_id' => $userId,
                        'type' => 'DISPENSATION',
                        'quantity' => -$take,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference_type' => 'DISPENSATION',
                        'reference_id' => $dispensation->id,
                        'idempotency_key' => $data['idempotency_key'],
                        'observations' => 'Dispensación de fórmula médica.',
                        'occurred_at' => now(),
                    ]);

                    $remainingToTake -= $take;
                }

                if ($controlledAuthorization) {
                    DB::table('controlled_medication_authorizations')
                        ->where('id', $controlledAuthorization->id)
                        ->update(['status' => 'USED', 'used_at' => now(), 'updated_at' => now()]);
                }

                $prescriptionItem->dispensed_quantity =
                    (int) $prescriptionItem->dispensed_quantity
                    + $quantityToDispense;

                $prescriptionItem->save();
            }

            return $dispensation->load('items.inventory.lot.medication');
        }, 3);
    }
}
