<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'medication_id',
        'prescribed_quantity',
        'dispensed_quantity',
        'dosage',
        'frequency',
        'duration_days',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_quantity' => 'integer',
            'dispensed_quantity' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Medication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function remainingQuantity(): int
    {
        return max(
            0,
            $this->prescribed_quantity - $this->dispensed_quantity
        );
    }
}
