<?php

use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an extraordinary maintenance is stored and immediately reflected on the dashboard', function () {
    $park = Park::create([
        'name' => 'Parque de prueba',
        'green_area_type' => 'Parque',
        'is_active' => true,
    ]);

    $this->actingAs(tenant())
        ->from('/mantenimientos')
        ->post('/mantenimientos/extraordinario', [
            'park_id' => $park->id,
            'maintenance_type' => 'Poda',
            'scheduled_date' => '2026-10-15',
            'advance_days' => 1,
            'reason' => 'Rama con riesgo de caída.',
            'worker_id' => '',
            'observations' => 'Atender con prioridad.',
        ])
        ->assertRedirect('/mantenimientos')
        ->assertSessionHasNoErrors();

    $maintenance = MaintenanceRecord::query()->sole();

    expect($maintenance)
        ->is_extraordinary->toBeTrue()
        ->maintenance_type->toBe('Poda')
        ->status->toBe('pendiente');

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('indicators.maintenances_scheduled_month', 1)
            ->where('priorityMaintenances.0.id', $maintenance->id));
});

test('an extraordinary maintenance rejects a type outside the active catalog', function () {
    $park = Park::create([
        'name' => 'Parque de prueba',
        'green_area_type' => 'Parque',
        'is_active' => true,
    ]);

    $this->actingAs(tenant())
        ->from('/mantenimientos')
        ->post('/mantenimientos/extraordinario', [
            'park_id' => $park->id,
            'maintenance_type' => 'Limpieza extraordinaria',
            'scheduled_date' => '2026-10-15',
            'advance_days' => 1,
            'reason' => 'Prueba de validación.',
        ])
        ->assertRedirect('/mantenimientos')
        ->assertSessionHasErrors('maintenance_type');

    expect(MaintenanceRecord::query()->count())->toBe(0);
});
