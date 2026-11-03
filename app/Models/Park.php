<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Park extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'code',
        'name',
        'green_area_type',
        'registration_date',
        'address',
        'zone',
        'reference',
        'latitude',
        'longitude',
        'public_use_area',
        'green_area',
        'total_area',
        'has_water_point',
        'water_supply_type',
        'water_point_status',
        'water_point_observations',
        'conservation_status',
        'photo_url',
        'general_observations',
        'is_active',
        'is_demo',
        // Compatibilidad previa
        'description',
        'status',
        'area',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'public_use_area' => 'decimal:2',
            'green_area' => 'decimal:2',
            'total_area' => 'decimal:2',
            'area' => 'decimal:2',
            'has_water_point' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Park $park) {
            // Generación automática del código si no está asignado
            if (empty($park->code)) {
                $maxNum = 0;
                $allCodes = static::pluck('code');
                foreach ($allCodes as $c) {
                    if (preg_match('/PRQ-(\d+)/', (string) $c, $matches)) {
                        $maxNum = max($maxNum, (int) $matches[1]);
                    }
                }
                $park->code = 'PRQ-' . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
            }

            if (empty($park->registration_date)) {
                $park->registration_date = now()->toDateString();
            }

            // Sincronizar compatibilidad
            if (empty($park->green_area) && !empty($park->area)) {
                $park->green_area = $park->area;
            } elseif (empty($park->area) && !empty($park->green_area)) {
                $park->area = $park->green_area;
            }

            if (empty($park->conservation_status) && !empty($park->status)) {
                $park->conservation_status = $park->status;
            } elseif (empty($park->status) && !empty($park->conservation_status)) {
                $park->status = $park->conservation_status;
            }
        });

        static::created(function (Park $park) {
            // Automatización: Reglas automáticas según el tipo de área verde
            $type = $park->green_area_type ?: 'Parque';
            $rules = MaintenanceRule::where('green_area_type', $type)
                ->where('is_active', true)
                ->get();

            $baseDate = $park->registration_date ? Carbon::parse($park->registration_date) : now();

            foreach ($rules as $rule) {
                $nextDate = $baseDate->copy()->addDays($rule->frequency_days);

                $schedule = $park->schedules()->create([
                    'maintenance_type' => $rule->maintenance_type,
                    'frequency_days' => $rule->frequency_days,
                    'next_scheduled_date' => $nextDate->toDateString(),
                    'is_active' => true,
                ]);

                // Registrar el primer mantenimiento programado
                $park->maintenanceRecords()->create([
                    'park_schedule_id' => $schedule->id,
                    'maintenance_type' => $rule->maintenance_type,
                    'scheduled_date' => $nextDate->toDateString(),
                    'advance_days' => $rule->advance_days,
                    'status' => 'pendiente',
                    'is_extraordinary' => false,
                ]);
            }
        });
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ParkSchedule::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
