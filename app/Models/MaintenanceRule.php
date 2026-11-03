<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRule extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'green_area_type',
        'maintenance_type',
        'frequency_days',
        'advance_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency_days' => 'integer',
            'advance_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
