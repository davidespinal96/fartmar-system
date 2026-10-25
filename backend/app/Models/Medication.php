<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'active_ingredient',
        'presentation',
        'concentration',
        'requires_prescription',
        'is_controlled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
