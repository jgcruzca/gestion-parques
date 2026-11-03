<?php

use App\Models\DashboardMonthlyTarget;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to the login page', function () {
    auth()->forgetUser();
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard shows executive indicators trends alerts and priorities', function () {
    $activePark = Park::create([
        'name' => 'Parque Central',
        'green_area_type' => 'Parque',
        'is_active' => true,
        'has_water_point' => true,
    ]);
    $inactivePark = Park::create([
        'name' => 'Plaza Norte',
        'green_area_type' => 'Plaza',
        'is_active' => false,
        'has_water_point' => false,
    ]);

    MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Poda',
        'scheduled_date' => '2026-09-20',
        'execution_date' => '2026-09-20',
        'status' => 'finalizado',
    ]);
    MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Poda',
        'scheduled_date' => '2026-10-30',
        'alert_date' => '2026-10-25',
        'advance_days' => 5,
        'status' => 'pendiente',
    ]);
    MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-17',
        'alert_date' => '2026-10-15',
        'advance_days' => 2,
        'status' => 'pendiente',
    ]);
    MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Limpieza',
        'scheduled_date' => '2026-10-15',
        'alert_date' => '2026-10-14',
        'status' => 'pendiente',
    ]);
    MaintenanceRecord::create([
        'park_id' => $inactivePark->id,
        'maintenance_type' => 'Fumigación',
        'scheduled_date' => '2026-10-10',
        'alert_date' => '2026-10-08',
        'status' => 'pendiente',
    ]);
    $inProgress = MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Corte de césped',
        'scheduled_date' => '2026-10-15',
        'execution_date' => '2026-10-15',
        'status' => 'en_proceso',
    ]);
    MaintenanceRecord::create([
        'park_id' => $activePark->id,
        'maintenance_type' => 'Deshierbe',
        'scheduled_date' => '2026-10-05',
        'execution_date' => '2026-10-05',
        'status' => 'finalizado',
    ]);

    $this->actingAs(tenant())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('indicators.parks_total', 2)
            ->where('indicators.parks_active', 1)
            ->where('indicators.water_coverage', 50)
            ->where('indicators.maintenances_overdue', 1)
            ->where('indicators.maintenances_in_progress', 1)
            ->where('indicators.maintenances_completed_month', 1)
            ->where('indicators.maintenances_scheduled_month', 6)
            ->where('monthlyPerformance.completion_rate', 17)
            ->where('monthlyPerformance.park_coverage_rate', 100)
            ->where('monthlyTarget.maintenance_goal', 6)
            ->where('statusDistribution.0.value', 1)
            ->has('priorityMaintenances', 4)
            ->where('priorityMaintenances.2.id', $inProgress->id)
            ->where('priorityMaintenances.2.status', 'en_proceso')
            ->has('notifications', 2)
            ->has('monthlyTrend', 6));
});

test('dashboard priority list includes delay labels and critical notifications', function () {
    $park = Park::create([
        'name' => 'Alameda Municipal',
        'green_area_type' => 'Alameda',
        'is_active' => true,
    ]);

    MaintenanceRecord::create([
        'park_id' => $park->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-01',
        'execution_date' => '2026-10-01',
        'status' => 'finalizado',
    ]);
    $overdue = MaintenanceRecord::create([
        'park_id' => $park->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-12',
        'alert_date' => '2026-10-10',
        'status' => 'pendiente',
    ]);

    $this->actingAs(tenant())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('priorityMaintenances.0.id', $overdue->id)
            ->where('priorityMaintenances.0.remaining_days', -3)
            ->where('priorityMaintenances.0.remaining_label', '3 días tarde')
            ->where('priorityMaintenances.0.status', 'vencido')
            ->where('notifications.0.id', $overdue->id)
            ->where('notifications.0.severity', 'critical'));
});

test('monthly dashboard targets can be defined and persist', function () {
    $this->actingAs(tenant())
        ->post('/dashboard/metas', [
            'month' => '2026-10',
            'maintenance_goal' => 24,
            'overdue_limit' => 2,
            'park_coverage_goal' => 90,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DashboardMonthlyTarget::query()->firstOrFail())
        ->maintenance_goal->toBe(24)
        ->overdue_limit->toBe(2)
        ->park_coverage_goal->toBe(90);
});
