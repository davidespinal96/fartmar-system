<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAssistantController extends Controller
{
    public function ask(Request $request, InventoryAssistantService $service): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);

        return response()->json($service->ask($data['question'], (int) $request->user()->id));
    }
}
