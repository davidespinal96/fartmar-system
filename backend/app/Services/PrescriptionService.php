<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    public function create(array $data, int $doctorId): Prescription
    {
        return DB::transaction(function () use ($data, $doctorId) {
            $patient = Patient::whereKey($data['patient_id'])->where('is_active', true)->lockForUpdate()->first();
            if (! $patient) {
                throw ValidationException::withMessages(['patient_id' => 'El paciente no existe o está inactivo.']);
            }

            $prescription = Prescription::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctorId,
                'prescription_number' => $data['prescription_number'],
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'] ?? null,
                'status' => 'ACTIVA',
                'observations' => $data['observations'] ?? null,
            ]);
            $prescription->items()->createMany($data['items']);
            DB::table('patient_access_logs')->insert([
                'user_id' => $doctorId, 'patient_id' => $patient->id,
                'action' => 'PRESCRIPTION_CREATED', 'accessed_at' => now(),
            ]);

            return $prescription->load('items.medication');
        }, 3);
    }
}
