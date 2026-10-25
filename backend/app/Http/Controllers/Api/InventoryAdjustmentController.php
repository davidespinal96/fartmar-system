<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAdjustmentController extends Controller
{
    public function store(int $inventoryId, Request $request, InventoryAdjustmentService $service): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return response()->json([
            'message' => 'Ajuste registrado en el kardex.',
            'data' => $service->adjust($inventoryId, $data['quantity'], $data['reason'], (int) $request->user()->id),
        ], 201);
    }
}
