<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAudit extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'maintenance_record_id',
        'user_id',
        'event',
        'reason',
        'document_path',
        'document_name',
        'previous_status',
        'scheduled_date',
        'overdue_days',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'overdue_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class, 'maintenance_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
