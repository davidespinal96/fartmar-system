<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDispensationRequest;
use App\Models\Dispensation;
use App\Services\DispensationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class DispensationController extends Controller
{
    /**
     * Consultar el historial de dispensaciones.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->query('per_page', 10), 1),
            100
        );

        $dispensations = Dispensation::query()
            ->with([
                'prescription:id,prescription_number',
                'patient:id',
                'items',
            ])
            ->latest('dispensed_at')
            ->paginate($perPage);

        if ($request->user()->role === 'auditor') {
            $dispensations->getCollection()->each(function (Dispensation $dispensation) {
                $dispensation->setAttribute('patient_id', null);
                $dispensation->setAttribute('prescription_id', null);
                $dispensation->setRelation('patient', null);
                $dispensation->setAttribute('observations', null);
            });
        }

        return response()->json([
            'message' => 'Dispensaciones consultadas correctamente.',
            'data' => $dispensations,
        ]);
    }

    /**
     * Registrar una dispensación.
     */
    public function store(
        StoreDispensationRequest $request,
        DispensationService $service
    ): JsonResponse {
        try {
            $dispensation = $service->dispense(
                $request->validated(),
                (int) $request->user()->id
            );

            return response()->json([
                'message' => 'Dispensación procesada correctamente.',
                'data' => $dispensation,
            ], 201);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible procesar la dispensación.',
            ], 500);
        }
    }
}
