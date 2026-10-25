<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlledMedicationAuthorizationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prescription_item_id' => ['required', 'integer', 'exists:prescription_items,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'authorization_key' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $item = PrescriptionItem::with(['prescription', 'medication'])->findOrFail($data['prescription_item_id']);
        if (! $item->medication->is_controlled || $item->prescription->status !== 'ACTIVA'
            || ($item->prescription->valid_until !== null && today()->gt($item->prescription->valid_until))
            || $item->remainingQuantity() < $data['quantity']) {
            throw ValidationException::withMessages(['prescription_item_id' => 'La línea no requiere autorización o no tiene cantidad vigente suficiente.']);
        }

        $authorization = DB::table('controlled_medication_authorizations')
            ->where('authorization_key', $data['authorization_key'])->first();
        if ($authorization) {
            $same = (int) $authorization->prescription_item_id === (int) $data['prescription_item_id']
                && (int) $authorization->warehouse_id === (int) $data['warehouse_id']
                && (int) $authorization->quantity === (int) $data['quantity']
                && (int) $authorization->authorized_by === (int) $request->user()->id;
            if (! $same) {
                return response()->json(['message' => 'La clave de autorización ya fue usada con otros datos.'], 409);
            }

            return response()->json(['data' => $authorization, 'message' => 'Autorización ya registrada.']);
        }
        $id = DB::table('controlled_medication_authorizations')->insertGetId([
            'prescription_item_id' => $data['prescription_item_id'],
            'warehouse_id' => $data['warehouse_id'],
            'quantity' => $data['quantity'],
            'authorized_by' => $request->user()->id,
            'authorization_key' => $data['authorization_key'],
            'status' => 'AUTHORIZED',
            'reason' => $data['reason'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Autorización aprobada por regente. Entrega el identificador al auxiliar que dispensa.',
            'data' => DB::table('controlled_medication_authorizations')->find($id),
        ], 201);
    }
}
