<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;

class PrescriptionController extends Controller
{
    public function store(StorePrescriptionRequest $request, PrescriptionService $service): JsonResponse
    {
        $prescription = $service->create($request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Fórmula creada.', 'data' => (new PrescriptionResource($prescription))->resolve($request)], 201);
    }
}
