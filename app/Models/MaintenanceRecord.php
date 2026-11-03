<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRecord extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'maintenance_number',
        'park_id',
        'worker_id',
        'park_schedule_id',
        'maintenance_type',
        'is_extraordinary',
        'reason',
        'scheduled_date',
        'alert_date',
        'advance_days',
        'execution_date',
        'responsible_worker',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
        'initial_observation',
        'work_description',
        'result',
        'photo_before_url',
        'photo_after_url',
        'observations',
        'document_name',
        'document_url',
    ];

    protected function casts(): array
    {
        return [
            'is_extraordinary' => 'boolean',
            'scheduled_date' => 'date',
            'alert_date' => 'date',
            'execution_date' => 'date',
            'advance_days' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRecord $record) {
            // Generar número correlativo PM-XXXXXX-YYYY si no está definido
            if (empty($record->maintenance_number)) {
                $year = $record->scheduled_date ? Carbon::parse($record->scheduled_date)->format('Y') : date('Y');
                $maxId = (int) MaintenanceRecord::max('id') + 1;
                $record->maintenance_number = sprintf('PM-%06d-%s', $maxId, $year);
            }

            // Calcular fecha de alerta automática si hay fecha programada
            if (!empty($record->scheduled_date) && empty($record->alert_date)) {
                $days = $record->advance_days ?? 1;
                $record->alert_date = Carbon::parse($record->scheduled_date)->subDays($days)->toDateString();
            }
        });

        static::saving(function (MaintenanceRecord $record) {
            if (!$record->isDirty('responsible_worker')) {
                return;
            }

            $selection = trim((string) $record->responsible_worker);

            if ($selection === '' || $selection === 'Por asignar') {
                $record->worker_id = null;

                return;
            }

            $worker = Worker::query()
                ->get(['id', 'first_name', 'last_name'])
                ->first(fn (Worker $candidate) => strcasecmp($candidate->full_name, $selection) === 0);

            $record->worker_id = $worker?->id;

            if ($worker) {
                $record->responsible_worker = $worker->full_name;
            }
        });
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ParkSchedule::class, 'park_schedule_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(MaintenanceAudit::class);
    }

    public function operationalStatus(?Carbon $today = null): string
    {
        $today ??= Carbon::today();

        if (in_array($this->status, ['finalizado', 'completado', 'ejecutado'], true)) {
            return 'finalizado';
        }

        if (in_array($this->status, ['en_proceso', 'en_mantenimiento'], true)) {
            return 'en_proceso';
        }

        if ($this->status === 'cancelado') {
            return 'cancelado';
        }

        if (!$this->scheduled_date) {
            return 'al_dia';
        }

        if ($this->scheduled_date->isBefore($today)) {
            return 'vencido';
        }

        if ($this->scheduled_date->isSameDay($today)) {
            return 'pendiente';
        }

        if ($this->alert_date && !$today->isBefore($this->alert_date)) {
            return 'proximo';
        }

        return 'al_dia';
    }
}
