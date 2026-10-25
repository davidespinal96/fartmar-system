<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prescription */
class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_number' => $this->prescription_number,
            'issued_at' => $this->issued_at?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'medication_id' => $item->medication_id,
                'medication_name' => $item->medication?->name,
                'is_controlled' => (bool) $item->medication?->is_controlled,
                'prescribed_quantity' => (int) $item->prescribed_quantity,
                'remaining_quantity' => $item->remainingQuantity(),
            ]),
        ];
    }
}
