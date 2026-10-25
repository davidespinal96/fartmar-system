<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Services\InventoryTransferService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventoryTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transfers = InventoryTransfer::with([
            'sourceWarehouse:id,code,name',
            'destinationWarehouse:id,code,name',
            'requester:id,name',
            'items.inventory.lot.medication',
        ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->latest()
            ->paginate(min((int) $request->input('per_page', 15), 100));

        return response()->json($transfers);
    }

    public function store(
        Request $request,
        InventoryTransferService $service
    ): JsonResponse {
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => [
                'required',
                'integer',
                'different:source_warehouse_id',
                'exists:warehouses,id',
            ],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => [
                'required',
                'integer',
                'distinct',
                'exists:inventories,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $existing = InventoryTransfer::where(
                'idempotency_key',
                $data['idempotency_key']
            )->exists();

            $transfer = $service->create(
                $data,
                (int) $request->user()->id
            );

            return response()->json([
                'message' => $existing
                    ? 'La solicitud ya había sido registrada.'
                    : 'Solicitud de transferencia registrada.',
                'data' => $transfer,
            ], $existing ? 200 : 201);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible procesar la transferencia. Intenta nuevamente.',
            ], 409);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible registrar la transferencia.',
            ], 500);
        }
    }

    public function approve(
        int $id,
        Request $request,
        InventoryTransferService $service
    ): JsonResponse {
        return $this->executeTransition(
            fn () => $service->approve($id, (int) $request->user()->id),
            'Transferencia aprobada.'
        );
    }

    public function submit(int $id, Request $request, InventoryTransferService $service): JsonResponse
    {
        return $this->executeTransition(
            fn () => $service->submit($id, (int) $request->user()->id),
            'Solicitud enviada para aprobación.'
        );
    }

    public function dispatch(
        int $id,
        Request $request,
        InventoryTransferService $service
    ): JsonResponse {
        return $this->executeTransition(
            fn () => $service->dispatch($id, (int) $request->user()->id),
            'Transferencia despachada.'
        );
    }

    public function receive(
        int $id,
        Request $request,
        InventoryTransferService $service
    ): JsonResponse {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.received_quantity' => ['required', 'integer', 'min:0'],
            'items.*.discrepancy_reason' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->executeTransition(
            fn () => $service->receive($id, (int) $request->user()->id, $data['items'], $data['reason'] ?? null),
            'Transferencia recibida.'
        );
    }

    public function cancel(int $id, Request $request, InventoryTransferService $service): JsonResponse
    {
        return $this->executeTransition(fn () => $service->cancel($id, (int) $request->user()->id), 'Solicitud de traslado anulada.');
    }

    private function executeTransition(
        callable $operation,
        string $message
    ): JsonResponse {
        try {
            return response()->json([
                'message' => $message,
                'data' => $operation(),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible completar la operación.',
            ], 500);
        }
    }
}
