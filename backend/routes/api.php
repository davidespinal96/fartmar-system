<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ControlledMedicationAuthorizationController;
use App\Http\Controllers\Api\DispensationController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\InventoryAssistantController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\PatientAccessLogController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/ready', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ready']);
    } catch (Throwable) {
        return response()->json(['status' => 'not_ready'], 503);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
        ]);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', function (Request $request) {
            return response()->json([
                'message' => 'Acceso autorizado al panel de administración.',
                'user' => $request->user()->name,
                'role' => $request->user()->role,
            ]);
        });
    });

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');

    Route::post('/inventory/{inventoryId}/adjustments', [InventoryAdjustmentController::class, 'store'])
        ->middleware('can:inventory.adjust');

    Route::get('/movements', [InventoryMovementController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');

    Route::get('/patients', [PatientController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor,medico');

    Route::get('/patients/{patientId}/prescriptions', [PatientController::class, 'prescriptions'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,medico');

    Route::post('/prescriptions', [PrescriptionController::class, 'store'])
        ->middleware('can:prescription.create');

    Route::get('/audit/patient-access', [PatientAccessLogController::class, 'index'])
        ->middleware('role:admin,auditor');

    Route::post('/inventory-assistant', [InventoryAssistantController::class, 'ask'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');

    Route::get('/dispensations', [DispensationController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');

    Route::post('/dispensations', [DispensationController::class, 'store'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin');

    Route::post('/controlled-authorizations', [ControlledMedicationAuthorizationController::class, 'store'])
        ->middleware('can:controlled-medication.authorize');

    Route::get('/transfers', [InventoryTransferController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');

    Route::post('/transfers', [InventoryTransferController::class, 'store'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin');

    Route::post('/transfers/{id}/approve', [InventoryTransferController::class, 'approve'])
        ->middleware('can:transfer.approve');

    Route::post('/transfers/{id}/submit', [InventoryTransferController::class, 'submit'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin');

    Route::post('/transfers/{id}/dispatch', [InventoryTransferController::class, 'dispatch'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin');

    Route::post('/transfers/{id}/receive', [InventoryTransferController::class, 'receive'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin');

    Route::post('/transfers/{id}/cancel', [InventoryTransferController::class, 'cancel'])
        ->middleware('can:transfer.approve');

    Route::get('/warehouses', [WarehouseController::class, 'index'])
        ->middleware('role:auxiliar_farmacia,regente_farmacia,admin,auditor');
});
