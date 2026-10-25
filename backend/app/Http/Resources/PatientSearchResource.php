<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($request->user()?->role === 'auditor') {
            return [
                'id' => $this->id,
                'display_name' => 'Paciente '.substr((string) $this->id, -4),
                'document_number' => '••••••'.substr($this->document_number, -2),
            ];
        }

        return [
            'id' => $this->id,
            'display_name' => $this->first_name.' '.$this->last_name,
            'document_number' => $this->document_number,
        ];
    }
}
