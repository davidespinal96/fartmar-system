<?php

namespace Database\Seeders;

use App\Models\Medication;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SyntheticPrescriptionSeeder extends Seeder
{
    public function run(): void
    {
        $patients = [
            ['document_type' => 'CC', 'document_number' => '900000001', 'first_name' => 'Paciente', 'last_name' => 'Sintético Uno', 'birth_date' => '1985-04-12'],
            ['document_type' => 'CC', 'document_number' => '900000002', 'first_name' => 'Paciente', 'last_name' => 'Sintético Dos', 'birth_date' => '1990-08-20'],
            ['document_type' => 'CC', 'document_number' => '900000003', 'first_name' => 'Paciente', 'last_name' => 'Sintético Tres', 'birth_date' => '1978-02-05'],
        ];
        $doctor = User::where('role', 'medico')->firstOrFail();
        foreach ($patients as $index => $data) {
            $patient = Patient::firstOrCreate(['document_number' => $data['document_number']], $data);
            $prescription = Prescription::firstOrCreate(['prescription_number' => 'RX-DEMO-'.($index + 1)], [
                'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
                'issued_at' => today(), 'valid_until' => today()->addDays(30), 'status' => 'ACTIVA',
            ]);
            $code = $index === 0 ? 'MED-004' : 'MED-001';
            $medication = Medication::where('code', $code)->firstOrFail();
            $prescription->items()->firstOrCreate(['medication_id' => $medication->id], [
                'prescribed_quantity' => 20, 'dispensed_quantity' => 0,
                'dosage' => 'Según indicación médica', 'frequency' => 'Cada 8 horas', 'duration_days' => 7,
            ]);
        }
    }
}
