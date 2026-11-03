<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;

class DashboardMonthlyTarget extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'month',
        'maintenance_goal',
        'overdue_limit',
        'park_coverage_goal',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'maintenance_goal' => 'integer',
            'overdue_limit' => 'integer',
            'park_coverage_goal' => 'integer',
        ];
    }
}
