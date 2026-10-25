<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientSearchResource;
use App\Http\Resources\PrescriptionResource;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['search']);
        $likeTerm = '%'.mb_strtolower($term).'%';
        $patients = Patient::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('document_number', 'like', "%{$term}%")
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$likeTerm])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$likeTerm]))
            ->orderBy('last_name')->limit(20)->get();
        foreach ($patients as $patient) {
            DB::table('patient_access_logs')->insert([
                'user_id' => $request->user()->id, 'patient_id' => $patient->id,
                'action' => 'SEARCH_RESULT', 'accessed_at' => now(),
            ]);
        }

        return PatientSearchResource::collection($patients)->response();
    }

    public function prescriptions(Request $request, int $patientId): JsonResponse
    {
        $patient = Patient::where('is_active', true)->findOrFail($patientId);
        DB::table('patient_access_logs')->insert([
            'user_id' => $request->user()->id, 'patient_id' => $patient->id,
            'action' => 'PRESCRIPTIONS_VIEW', 'accessed_at' => now(),
        ]);
        $prescriptions = Prescription::with('items.medication')->where('patient_id', $patient->id)
            ->latest('issued_at')->get();

        return PrescriptionResource::collection($prescriptions)->response();
    }
}
