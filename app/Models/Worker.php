<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'code',
        'document_number',
        'first_name',
        'last_name',
        'position',
        'phone',
        'crew',
        'observations',
        'is_active',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected static function booted(): void
    {
        static::creating(function (Worker $worker) {
            if (empty($worker->code)) {
                $maxNumber = static::query()
                    ->pluck('code')
                    ->reduce(function (int $max, ?string $code) {
                        return preg_match('/^TRB-(\d+)$/', (string) $code, $matches)
                            ? max($max, (int) $matches[1])
                            : $max;
                    }, 0);

                $worker->code = sprintf('TRB-%04d', $maxNumber + 1);
            }
        });
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
