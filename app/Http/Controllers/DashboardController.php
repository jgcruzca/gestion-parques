<?php

namespace App\Http\Controllers;

use App\Models\DashboardMonthlyTarget;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $parks = Park::query()->get();
        $records = MaintenanceRecord::query()
            ->with('park')
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        $recordsByState = $records->groupBy(
            fn (MaintenanceRecord $record) => $record->operationalStatus($today)
        );

        $scheduledThisMonth = $records->filter(
            fn (MaintenanceRecord $record) => $record->scheduled_date
                && $record->scheduled_date->isSameMonth($today)
                && $record->scheduled_date->isSameYear($today)
        );
        $completedThisMonth = $records->filter(
            fn (MaintenanceRecord $record) => $record->operationalStatus($today) === 'finalizado'
                && $record->execution_date
                && $record->execution_date->isSameMonth($today)
                && $record->execution_date->isSameYear($today)
        );
        $activeParkCount = $parks->where('is_active', true)->count();
        $coveredParkCount = $completedThisMonth->pluck('park_id')->filter()->unique()->count();
        $onTimeCount = $completedThisMonth->filter(
            fn (MaintenanceRecord $record) => $record->scheduled_date
                && $record->execution_date
                && !$record->execution_date->isAfter($record->scheduled_date)
        )->count();

        // Leer el objetivo sin escribir durante el GET evita inserciones duplicadas
        // cuando el navegador realiza varias recargas simultáneas.
        $target = DashboardMonthlyTarget::query()
            ->whereDate('month', $monthStart->toDateString())
            ->first();

        $priorityMaintenances = $records
            ->filter(fn (MaintenanceRecord $record) => in_array(
                $record->operationalStatus($today),
                ['vencido', 'pendiente', 'en_proceso', 'proximo'],
                true,
            ))
            ->sortBy(function (MaintenanceRecord $record) use ($today) {
                $priority = match ($record->operationalStatus($today)) {
                    'vencido' => 0,
                    'pendiente' => 1,
                    'en_proceso' => 2,
                    default => 3,
                };

                return sprintf('%d-%s-%010d', $priority, $record->scheduled_date?->format('Y-m-d') ?? '9999-12-31', $record->id);
            })
            ->take(6)
            ->map(fn (MaintenanceRecord $record) => $this->maintenanceRow($record, $today))
            ->values();

        $notifications = $records
            ->filter(fn (MaintenanceRecord $record) => in_array($record->operationalStatus($today), ['vencido', 'pendiente'], true))
            ->sortBy(fn (MaintenanceRecord $record) => $record->scheduled_date?->timestamp ?? PHP_INT_MAX)
            ->take(6)
            ->map(function (MaintenanceRecord $record) use ($today) {
                $days = $record->scheduled_date ? abs((int) $today->diffInDays($record->scheduled_date, false)) : 0;
                $isOverdue = $record->operationalStatus($today) === 'vencido';

                return [
                    'id' => $record->id,
                    'title' => $isOverdue ? 'Mantenimiento vencido' : 'Mantenimiento para hoy',
                    'description' => ($record->park?->name ?? 'Parque no disponible').' · '.$record->maintenance_type,
                    'meta' => $isOverdue ? $days.' día'.($days === 1 ? '' : 's').' de retraso' : 'Requiere atención hoy',
                    'severity' => $isOverdue ? 'critical' : 'warning',
                    'href' => '/mantenimientos?atender='.$record->id,
                ];
            })
            ->values();

        $institution = InstitutionalSetting::query()->first();
        $totalGreenArea = (float) $parks->sum(fn (Park $park) => (float) ($park->green_area ?? $park->area ?? 0));
        $waterCoverage = $parks->count() > 0
            ? (int) round(($parks->where('has_water_point', true)->count() / $parks->count()) * 100)
            : 0;

        return Inertia::render('dashboard', [
            'institution' => [
                'organization_name' => $institution?->organization_name ?? 'Mi organización',
                'unit_name' => $institution?->unit_name ?? 'Área de Parques y Mantenimiento',
            ],
            'indicators' => [
                'parks_total' => $parks->count(),
                'parks_active' => $activeParkCount,
                'total_green_area' => round($totalGreenArea, 1),
                'water_coverage' => $waterCoverage,
                'maintenances_overdue' => $recordsByState->get('vencido', collect())->count(),
                'maintenances_in_progress' => $recordsByState->get('en_proceso', collect())->count(),
                'maintenances_completed_month' => $completedThisMonth->count(),
                'maintenances_scheduled_month' => $scheduledThisMonth->count(),
            ],
            'monthlyPerformance' => [
                'month' => $monthStart->format('Y-m'),
                'month_label' => ucfirst($monthStart->locale('es')->translatedFormat('F Y')),
                'completed' => $completedThisMonth->count(),
                'scheduled' => $scheduledThisMonth->count(),
                'completion_rate' => $scheduledThisMonth->count() > 0
                    ? min(100, (int) round(($completedThisMonth->count() / $scheduledThisMonth->count()) * 100))
                    : 0,
                'on_time_rate' => $completedThisMonth->count() > 0
                    ? (int) round(($onTimeCount / $completedThisMonth->count()) * 100)
                    : 0,
                'park_coverage_rate' => $activeParkCount > 0
                    ? min(100, (int) round(($coveredParkCount / $activeParkCount) * 100))
                    : 0,
            ],
            'monthlyTarget' => [
                'maintenance_goal' => $target?->maintenance_goal ?? max($scheduledThisMonth->count(), 1),
                'overdue_limit' => $target?->overdue_limit ?? 0,
                'park_coverage_goal' => $target?->park_coverage_goal ?? 80,
            ],
            'statusDistribution' => [
                ['key' => 'overdue', 'label' => 'Vencidos', 'value' => $recordsByState->get('vencido', collect())->count(), 'color' => '#e11d48'],
                ['key' => 'today', 'label' => 'Para hoy', 'value' => $recordsByState->get('pendiente', collect())->count(), 'color' => '#f59e0b'],
                ['key' => 'progress', 'label' => 'En proceso', 'value' => $recordsByState->get('en_proceso', collect())->count(), 'color' => '#0ea5e9'],
                ['key' => 'upcoming', 'label' => 'Próximos', 'value' => $recordsByState->get('proximo', collect())->count(), 'color' => '#8b5cf6'],
                ['key' => 'ok', 'label' => 'Al día', 'value' => $recordsByState->get('al_dia', collect())->count(), 'color' => '#10b981'],
            ],
            'monthlyTrend' => $this->monthlyTrend($records, $monthStart),
            'priorityMaintenances' => $priorityMaintenances,
            'notifications' => $notifications,
        ]);
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'maintenance_goal' => ['required', 'integer', 'min:1', 'max:9999'],
            'overdue_limit' => ['required', 'integer', 'min:0', 'max:9999'],
            'park_coverage_goal' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();

        $target = DashboardMonthlyTarget::query()
            ->whereDate('month', $month->toDateString())
            ->first();

        $values = [
            'maintenance_goal' => $validated['maintenance_goal'],
            'overdue_limit' => $validated['overdue_limit'],
            'park_coverage_goal' => $validated['park_coverage_goal'],
        ];

        if ($target) {
            $target->update($values);
        } else {
            DashboardMonthlyTarget::query()->create([
                'month' => $month->toDateString(),
                ...$values,
            ]);
        }

        return back()->with('success', 'Metas mensuales actualizadas.');
    }

    private function monthlyTrend(Collection $records, Carbon $monthStart): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($records, $monthStart) {
                $month = $monthStart->copy()->subMonths($monthsAgo);
                $scheduled = $records->filter(fn (MaintenanceRecord $record) => $record->scheduled_date
                    && $record->scheduled_date->isSameMonth($month)
                    && $record->scheduled_date->isSameYear($month))->count();
                $completed = $records->filter(fn (MaintenanceRecord $record) => $record->execution_date
                    && $record->execution_date->isSameMonth($month)
                    && $record->execution_date->isSameYear($month)
                    && in_array($record->status, ['finalizado', 'completado', 'ejecutado'], true))->count();

                return [
                    'month' => ucfirst($month->locale('es')->translatedFormat('M')),
                    'scheduled' => $scheduled,
                    'completed' => $completed,
                ];
            })
            ->values()
            ->all();
    }

    private function maintenanceRow(MaintenanceRecord $record, Carbon $today): array
    {
        $remainingDays = $record->scheduled_date
            ? (int) $today->diffInDays($record->scheduled_date, false)
            : null;

        return [
            'id' => $record->id,
            'maintenance_number' => $record->maintenance_number,
            'park_name' => $record->park?->name ?? 'Parque no disponible',
            'maintenance_type' => $record->maintenance_type,
            'next_date' => $record->scheduled_date?->format('d/m/Y'),
            'remaining_days' => $remainingDays,
            'remaining_label' => match (true) {
                $remainingDays === null => 'Sin fecha',
                $remainingDays > 0 => 'En '.$remainingDays.' día'.($remainingDays === 1 ? '' : 's'),
                $remainingDays === 0 => 'Hoy',
                default => abs($remainingDays).' día'.(abs($remainingDays) === 1 ? '' : 's').' tarde',
            },
            'status' => $record->operationalStatus($today),
            'is_in_progress' => $record->operationalStatus($today) === 'en_proceso',
        ];
    }
}
