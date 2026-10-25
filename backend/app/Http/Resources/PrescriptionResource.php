<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Prescription */
class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Carbon|null $issuedAt */
        $issuedAt = $this->issued_at;
        /** @var Carbon|null $validUntil */
        $validUntil = $this->valid_until;

        return [
            'id' => $this->id,
            'prescription_number' => $this->prescription_number,
            'issued_at' => $issuedAt?->toDateString(),
            'valid_until' => $validUntil?->toDateString(),
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
