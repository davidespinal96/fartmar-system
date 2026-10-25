<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function create(array $data, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = collect($data['items'])
                ->map(fn (array $item) => [
                    'inventory_id' => (int) $item['inventory_id'],
                    'quantity' => (int) $item['quantity'],
                ])
                ->sortBy('inventory_id')
                ->values();

            $existing = InventoryTransfer::with('items')
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $existingItems = $existing->items
                    ->map(fn ($item) => [
                        'inventory_id' => (int) $item->inventory_id,
                        'quantity' => (int) $item->quantity,
                    ])
                    ->sortBy('inventory_id')
                    ->values();

                $sameItems = $existingItems->all() === $items->all();

                $sameRequest =
                    (int) $existing->source_warehouse_id ===
                        (int) $data['source_warehouse_id']
                    && (int) $existing->destination_warehouse_id ===
                        (int) $data['destination_warehouse_id']
                    && (int) $existing->requested_by === $userId
                    && $existing->observations === ($data['observations'] ?? null)
                    && $sameItems;

                if (! $sameRequest) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'La clave de idempotencia ya fue utilizada con datos diferentes.',
                        ], 409)
                    );
                }

                return $existing;
            }

            if (
                (int) $data['source_warehouse_id'] ===
                (int) $data['destination_warehouse_id']
            ) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' => 'La bodega de origen y destino deben ser diferentes.',
                ]);
            }

            foreach ($items as $index => $item) {
                $inventory = Inventory::whereKey($item['inventory_id'])
                    ->where('warehouse_id', $data['source_warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        "items.{$index}.inventory_id" => 'El inventario no pertenece a la bodega de origen.',
                    ]);
                }

                $lot = $inventory->lot()->first();
                if (! $lot || ! $lot->is_active || $lot->expiration_date < today()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.inventory_id" => 'No se puede solicitar el traslado de un lote inactivo o vencido.',
                    ]);
                }

                if ($item['quantity'] < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'La cantidad debe ser mayor que cero.',
                    ]);
                }

                if ((int) $inventory->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Existencias insuficientes. Disponibles: {$inventory->quantity}; solicitadas: {$item['quantity']}.",
                    ]);
                }
            }

            $transfer = InventoryTransfer::create([
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'requested_by' => $userId,
                'status' => 'BORRADOR',
                'idempotency_key' => $data['idempotency_key'],
                'observations' => $data['observations'] ?? null,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create($item);
            }

            return $transfer->load('items');
        }, 3);
    }

    public function approve(int $transferId, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($transferId, $userId) {
            $transfer = InventoryTransfer::whereKey($transferId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->requested_by === $userId) {
                throw ValidationException::withMessages([
                    'transfer' => 'No puedes aprobar tu propia solicitud.',
                ]);
            }

            if ($transfer->status !== 'SOLICITADO') {
                throw ValidationException::withMessages([
                    'transfer' => 'Solo se pueden aprobar solicitudes pendientes.',
                ]);
            }

            $transfer->update([
                'status' => 'APROBADO',
                'approved_by' => $userId,
            ]);

            return $transfer->load('items');
        });
    }

    public function submit(int $transferId, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($transferId, $userId) {
            $transfer = InventoryTransfer::with('items')->whereKey($transferId)->lockForUpdate()->firstOrFail();
            if ((int) $transfer->requested_by !== $userId || $transfer->status !== 'BORRADOR') {
                throw ValidationException::withMessages(['transfer' => 'Solo quien creó el borrador puede enviarlo a aprobación.']);
            }
            foreach ($transfer->items as $index => $item) {
                $inventory = Inventory::whereKey($item->inventory_id)->lockForUpdate()->firstOrFail();
                $lot = $inventory->lot()->first();
                if ((int) $inventory->warehouse_id !== (int) $transfer->source_warehouse_id
                    || (int) $inventory->quantity < (int) $item->quantity
                    || ! $lot || ! $lot->is_active || $lot->expiration_date < today()) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => 'El inventario ya no tiene existencias suficientes o el lote está vencido/inactivo.',
                    ]);
                }
            }
            $transfer->update(['status' => 'SOLICITADO']);

            return $transfer->load('items');
        }, 3);
    }

    public function dispatch(int $transferId, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($transferId, $userId) {
            $transfer = InventoryTransfer::with('items')
                ->whereKey($transferId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'APROBADO') {
                throw ValidationException::withMessages([
                    'transfer' => 'La transferencia debe estar aprobada.',
                ]);
            }

            foreach ($transfer->items as $item) {
                $inventory = Inventory::whereKey($item->inventory_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $inventory->warehouse_id !== $transfer->source_warehouse_id
                    || $inventory->quantity < $item->quantity
                ) {
                    throw ValidationException::withMessages([
                        'inventory' => 'Existencias insuficientes o inventario de origen inválido.',
                    ]);
                }

                $lot = $inventory->lot()->first();

                if (
                    ! $lot
                    || ! $lot->is_active
                    || $lot->expiration_date < today()->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'inventory' => 'No se puede despachar un lote inactivo o vencido.',
                    ]);
                }

                $before = (int) $inventory->quantity;
                $inventory->quantity = $before - $item->quantity;
                $inventory->save();

                InventoryMovement::create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'TRANSFER_OUT',
                    'quantity' => -$item->quantity,
                    'balance_before' => $before,
                    'balance_after' => $inventory->quantity,
                    'reference_type' => 'INVENTORY_TRANSFER',
                    'reference_id' => $transfer->id,
                    'observations' => 'Despacho de transferencia.',
                    'occurred_at' => now(),
                ]);
            }

            $transfer->update([
                'status' => 'EN_TRANSITO',
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
            ]);

            return $transfer->load('items');
        }, 3);
    }

    public function cancel(int $transferId, int $userId): InventoryTransfer
    {
        return DB::transaction(function () use ($transferId, $userId) {
            $transfer = InventoryTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['BORRADOR', 'SOLICITADO', 'APROBADO'], true)) {
                throw ValidationException::withMessages(['transfer' => 'Solo se pueden anular solicitudes sin despachar. Resuelve primero cualquier discrepancia de un traslado en tránsito.']);
            }
            $transfer->update(['status' => 'ANULADO', 'cancelled_by' => $userId, 'cancelled_at' => now()]);

            return $transfer->load('items');
        }, 3);
    }

    public function receive(int $transferId, int $userId, array $receivedItems, ?string $reason = null): InventoryTransfer
    {
        return DB::transaction(function () use ($transferId, $userId, $receivedItems, $reason) {
            $transfer = InventoryTransfer::with('items')
                ->whereKey($transferId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($transfer->status, ['EN_TRANSITO', 'RECIBIDO_PARCIAL'], true)) {
                throw ValidationException::withMessages([
                    'transfer' => 'Solo se pueden recibir transferencias despachadas.',
                ]);
            }

            $quantities = collect($receivedItems)->keyBy('item_id');
            if ($quantities->count() !== $transfer->items->count()
                || $transfer->items->contains(fn ($item) => ! $quantities->has($item->id))) {
                throw ValidationException::withMessages(['items' => 'Incluye una cantidad recibida para cada línea del traslado.']);
            }

            $allReceived = true;
            foreach ($transfer->items as $item) {
                $receivedNow = (int) $quantities[$item->id]['received_quantity'];
                $outstanding = (int) $item->quantity - (int) $item->received_quantity;
                if ($receivedNow < 0 || $receivedNow > $outstanding) {
                    throw ValidationException::withMessages(['items' => "La cantidad de la línea {$item->id} supera lo pendiente por recibir ({$outstanding})."]);
                }
                $cumulative = (int) $item->received_quantity + $receivedNow;
                $allReceived = $allReceived && $cumulative === (int) $item->quantity;
                if ($cumulative < (int) $item->quantity && trim((string) ($quantities[$item->id]['discrepancy_reason'] ?? $reason ?? '')) === '') {
                    throw ValidationException::withMessages(['items' => 'Indica el motivo de cada cantidad pendiente de recibir.']);
                }
                $source = Inventory::whereKey($item->inventory_id)
                    ->firstOrFail();

                $destination = Inventory::firstOrCreate(
                    [
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'lot_id' => $source->lot_id,
                    ],
                    [
                        'quantity' => 0,
                        'minimum_stock' => 0,
                    ]
                );

                $destination = Inventory::whereKey($destination->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = (int) $destination->quantity;
                $destination->quantity = $before + $receivedNow;
                $destination->save();

                if ($receivedNow > 0) {
                    InventoryMovement::create([
                        'inventory_id' => $destination->id,
                        'user_id' => $userId,
                        'type' => 'TRANSFER_IN',
                        'quantity' => $receivedNow,
                        'balance_before' => $before,
                        'balance_after' => $destination->quantity,
                        'reference_type' => 'INVENTORY_TRANSFER',
                        'reference_id' => $transfer->id,
                        'observations' => 'Recepción de transferencia.'.($reason ? ' '.$reason : ''),
                        'occurred_at' => now(),
                    ]);
                }

                $item->received_quantity = $cumulative;
                $item->discrepancy_reason = $cumulative < (int) $item->quantity
                    ? ($quantities[$item->id]['discrepancy_reason'] ?? $reason)
                    : null;
                $item->save();
            }

            $transfer->update([
                'status' => $allReceived ? 'RECIBIDO' : 'RECIBIDO_PARCIAL',
                'received_by' => $userId,
                'received_at' => now(),
                'discrepancy_observations' => $allReceived ? null : $reason,
            ]);

            return $transfer->load('items');
        }, 3);
    }
}
