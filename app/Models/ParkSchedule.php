<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkSchedule extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'park_id',
        'maintenance_type',
        'frequency_days',
        'last_executed_at',
        'next_scheduled_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency_days' => 'integer',
            'last_executed_at' => 'date',
            'next_scheduled_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
