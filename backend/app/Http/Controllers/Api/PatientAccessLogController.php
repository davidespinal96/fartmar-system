<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientAccessLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = DB::table('patient_access_logs')->join('users', 'users.id', '=', 'patient_access_logs.user_id')
            ->select('patient_access_logs.id', 'patient_access_logs.user_id', 'users.name as user_name',
                'patient_access_logs.patient_id', 'patient_access_logs.action', 'patient_access_logs.accessed_at')
            ->orderByDesc('patient_access_logs.accessed_at');
        if (isset($filters['from'])) {
            $query->whereDate('accessed_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('accessed_at', '<=', $filters['to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 50));
    }
}
