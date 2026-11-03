<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Filtros del reporte de mantenimientos. Los comparten el módulo de reportes
 * y el asistente, de modo que ambos produzcan los mismos registros.
 */
class MaintenanceReportFilters
{
    /**
     * Estructura completa de filtros tal como la normaliza ReportController::filters().
     */
    public static function defaults(array $overrides = []): array
    {
        return array_merge([
            'month' => '',
            'date_from' => '',
            'date_to' => '',
            'park_id' => '',
            'park_ids' => '',
            'maintenance_type' => '',
            'worker_id' => '',
            'status' => '',
            'date_basis' => 'operational',
            'maintenance_origin' => '',
            'assignment' => '',
            'duration_min' => '',
            'duration_max' => '',
            'retraso_min' => '',
            'has_document' => '',
            'has_photo' => '',
            'search' => '',
        ], $overrides);
    }

    public static function apply(Builder|Relation $query, array $filters): Builder|Relation
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        if ($filters['month']) {
            $month = Carbon::createFromFormat('Y-m', $filters['month']);
            $dateFrom = $month->copy()->startOfMonth()->toDateString();
            $dateTo = $month->copy()->endOfMonth()->toDateString();
        }

        if ($dateFrom) {
            self::applyDateBoundary($query, $filters['date_basis'], '>=', $dateFrom);
        }

        if ($dateTo) {
            self::applyDateBoundary($query, $filters['date_basis'], '<=', $dateTo);
        }

        $query->when($filters['park_id'], fn (Builder $query, string $parkId) => $query->where('park_id', $parkId));
        $query->when($filters['park_ids'] ?? '', fn (Builder $query, string $ids) => $query
            ->whereIn('park_id', array_filter(array_map('intval', explode(',', $ids)))));
        $query->when($filters['maintenance_type'], fn (Builder $query, string $type) => $query->where('maintenance_type', $type));
        $query->when($filters['worker_id'], fn (Builder $query, string $workerId) => $query->where('worker_id', $workerId));
        $query->when($filters['maintenance_origin'], fn (Builder $query, string $origin) => $query
            ->where('is_extraordinary', $origin === 'extraordinary'));

        $query->when($filters['assignment'], function (Builder $query, string $assignment) {
            if ($assignment === 'assigned') {
                $query->where(function (Builder $query) {
                    $query->whereNotNull('worker_id')
                        ->orWhere(function (Builder $query) {
                            $query->whereNotNull('responsible_worker')
                                ->where('responsible_worker', '<>', '')
                                ->where('responsible_worker', '<>', 'Por asignar');
                        });
                });

                return;
            }

            $query->whereNull('worker_id')
                ->where(function (Builder $query) {
                    $query->whereNull('responsible_worker')
                        ->orWhere('responsible_worker', '')
                        ->orWhere('responsible_worker', 'Por asignar');
                });
        });

        $query->when($filters['duration_min'] !== '', fn (Builder $query) => $query
            ->where('duration_minutes', '>=', (int) $filters['duration_min']));
        $query->when($filters['duration_max'] !== '', fn (Builder $query) => $query
            ->where('duration_minutes', '<=', (int) $filters['duration_max']));

        $query->when(($filters['retraso_min'] ?? '') !== '', function (Builder $query) use ($filters) {
            $limite = Carbon::today()->subDays((int) $filters['retraso_min'])->toDateString();

            $query->whereNotIn('status', ['finalizado', 'completado', 'ejecutado', 'en_proceso', 'en_mantenimiento', 'cancelado'])
                ->whereDate('scheduled_date', '<=', $limite);
        });

        $query->when(($filters['has_document'] ?? '') !== '', fn (Builder $query) => $filters['has_document'] === '1'
            ? $query->whereNotNull('document_url')
            : $query->whereNull('document_url'));

        $query->when(($filters['has_photo'] ?? '') !== '', fn (Builder $query) => $filters['has_photo'] === '1'
            ? $query->whereNotNull('photo_after_url')
            : $query->whereNull('photo_after_url'));

        $query->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
            $like = '%'.$filters['search'].'%';

            $query->where(function (Builder $busqueda) use ($like) {
                $busqueda->where('observations', 'like', $like)
                    ->orWhere('work_description', 'like', $like)
                    ->orWhere('result', 'like', $like)
                    ->orWhere('initial_observation', 'like', $like)
                    ->orWhere('maintenance_number', 'like', $like);
            });
        });

        $query->when($filters['status'], function (Builder $query, string $status) {
            match ($status) {
                'proximo' => $query->where('status', 'pendiente')->whereDate('scheduled_date', '>', today()),
                'vencido' => $query->where('status', 'pendiente')->whereDate('scheduled_date', '<', today()),
                'pendiente' => $query->where('status', 'pendiente')->whereDate('scheduled_date', today()),
                'en_proceso' => $query->whereIn('status', ['en_proceso', 'en_mantenimiento']),
                'finalizado' => $query->whereIn('status', ['finalizado', 'completado', 'ejecutado']),
                'cancelado' => $query->where('status', 'cancelado'),
            };
        });

        return $query;
    }

    private static function applyDateBoundary(Builder|Relation $query, string $basis, string $operator, string $date): void
    {
        if ($basis === 'scheduled') {
            $query->whereDate('scheduled_date', $operator, $date);

            return;
        }

        if ($basis === 'executed') {
            $query->whereDate('execution_date', $operator, $date);

            return;
        }

        $query->where(function (Builder $query) use ($operator, $date) {
            $query->whereDate('execution_date', $operator, $date)
                ->orWhere(function (Builder $query) use ($operator, $date) {
                    $query->whereNull('execution_date')->whereDate('scheduled_date', $operator, $date);
                });
        });
    }
}
