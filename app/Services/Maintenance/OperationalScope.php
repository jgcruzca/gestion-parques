<?php

namespace App\Services\Maintenance;

use Illuminate\Database\Eloquent\Builder;

/**
 * Alcances de estado operativo de los mantenimientos. Los comparten la bandeja
 * de mantenimientos y el asistente para que ambos clasifiquen igual.
 *
 * "vencido" nunca se guarda: se deduce de la fecha programada de un registro
 * que no está finalizado, en proceso ni cancelado.
 */
class OperationalScope
{
    public const TERMINAL_STATUSES = ['finalizado', 'completado', 'ejecutado'];

    public const IN_PROGRESS_STATUSES = ['en_proceso', 'en_mantenimiento'];

    public static function pendingBase(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [...self::TERMINAL_STATUSES, ...self::IN_PROGRESS_STATUSES, 'cancelado'])
            ->whereNotNull('scheduled_date');
    }

    public static function overdue(Builder $query, string $todayStart): Builder
    {
        return self::pendingBase($query)->where('scheduled_date', '<', $todayStart);
    }

    public static function dueToday(Builder $query, string $todayStart, string $tomorrowStart): Builder
    {
        return self::pendingBase($query)
            ->where('scheduled_date', '>=', $todayStart)
            ->where('scheduled_date', '<', $tomorrowStart);
    }

    public static function upcoming(Builder $query, string $tomorrowStart): Builder
    {
        return self::pendingBase($query)
            ->where('scheduled_date', '>=', $tomorrowStart)
            ->whereNotNull('alert_date')
            ->where('alert_date', '<', $tomorrowStart);
    }

    /**
     * Bandeja operativa completa: en proceso, vencidos, para hoy y próximos.
     */
    public static function operational(Builder $query, string $tomorrowStart): Builder
    {
        return $query->where(function (Builder $operational) use ($tomorrowStart) {
            $operational->whereIn('status', self::IN_PROGRESS_STATUSES)
                ->orWhere(function (Builder $pending) use ($tomorrowStart) {
                    self::pendingBase($pending)
                        ->where(function (Builder $dateScope) use ($tomorrowStart) {
                            $dateScope->where('scheduled_date', '<', $tomorrowStart)
                                ->orWhere(function (Builder $upcoming) use ($tomorrowStart) {
                                    $upcoming->where('scheduled_date', '>=', $tomorrowStart)
                                        ->whereNotNull('alert_date')
                                        ->where('alert_date', '<', $tomorrowStart);
                                });
                        });
                });
        });
    }

    public static function inProgress(Builder $query): Builder
    {
        return $query->whereIn('status', self::IN_PROGRESS_STATUSES);
    }

    public static function finished(Builder $query): Builder
    {
        return $query->whereIn('status', self::TERMINAL_STATUSES);
    }
}
