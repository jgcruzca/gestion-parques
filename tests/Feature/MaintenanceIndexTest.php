<?php

use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-22 09:00:00');

    $this->user = tenant();
    $this->park = Park::create([
        'name' => 'Parque Central',
        'code' => 'PRQ-0100',
        'green_area_type' => 'Parque',
        'address' => 'Av. Principal 100',
        'zone' => 'Centro',
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function createMaintenanceForIndex(Park $park, array $attributes = []): MaintenanceRecord
{
    return MaintenanceRecord::create(array_merge([
        'park_id' => $park->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-22',
        'alert_date' => '2026-10-21',
        'status' => 'pendiente',
        'observations' => 'Dato pesado que no debe viajar en el listado.',
        'work_description' => 'Descripción completa del trabajo.',
        'photo_before_url' => 'maintenances/before/test.jpg',
    ], $attributes));
}

test('the operational inbox contains only actionable records ordered by priority', function () {
    $today = createMaintenanceForIndex($this->park);
    $upcoming = createMaintenanceForIndex($this->park, [
        'scheduled_date' => '2026-10-24',
        'alert_date' => '2026-10-22',
    ]);
    $overdue = createMaintenanceForIndex($this->park, [
        'scheduled_date' => '2026-10-20',
        'alert_date' => '2026-10-19',
    ]);
    $inProgress = createMaintenanceForIndex($this->park, [
        'scheduled_date' => '2026-10-21',
        'status' => 'en_proceso',
    ]);

    createMaintenanceForIndex($this->park, [
        'scheduled_date' => '2026-11-10',
        'alert_date' => '2026-11-09',
    ]);
    createMaintenanceForIndex($this->park, ['status' => 'finalizado']);
    createMaintenanceForIndex($this->park, ['status' => 'cancelado']);

    $this->actingAs($this->user)
        ->get('/mantenimientos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('maintenances/index')
            ->where('filters.vista', 'operativa')
            ->where('records.per_page', 25)
            ->where('records.total', 4)
            ->where('records.data.0.id', $inProgress->id)
            ->where('records.data.1.id', $overdue->id)
            ->where('records.data.2.id', $today->id)
            ->where('records.data.3.id', $upcoming->id)
            ->missing('records.data.0.observations')
            ->missing('records.data.0.work_description')
            ->missing('records.data.0.photo_before_url'));
});

test('history is separated from the operational inbox', function () {
    createMaintenanceForIndex($this->park, ['status' => 'pendiente']);
    $completed = createMaintenanceForIndex($this->park, [
        'status' => 'finalizado',
        'execution_date' => '2026-10-21',
    ]);
    $cancelled = createMaintenanceForIndex($this->park, ['status' => 'cancelado']);

    $this->actingAs($this->user)
        ->get('/mantenimientos?vista=historial')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.vista', 'historial')
            ->where('records.total', 2)
            ->where('records.data.0.id', $cancelled->id)
            ->where('records.data.1.id', $completed->id));
});

test('filters are applied in Laravel and park search covers code name zone and address', function () {
    $otherPark = Park::create([
        'name' => 'Parque Los Jardines',
        'code' => 'PRQ-0200',
        'green_area_type' => 'Parque',
        'address' => 'Jirón Las Flores',
        'zone' => 'Sur',
        'is_active' => true,
    ]);

    createMaintenanceForIndex($this->park, ['maintenance_type' => 'Poda']);
    $matching = createMaintenanceForIndex($otherPark, ['maintenance_type' => 'Riego']);

    $this->actingAs($this->user)
        ->get('/mantenimientos?search=PRQ-0200&maintenance_type=Riego')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'PRQ-0200')
            ->where('filters.maintenance_type', 'Riego')
            ->where('records.total', 1)
            ->where('records.data.0.id', $matching->id));
});

test('the inbox is paginated at twenty five records', function () {
    foreach (range(1, 30) as $day) {
        createMaintenanceForIndex($this->park, [
            'scheduled_date' => '2026-10-22',
            'maintenance_type' => "Labor {$day}",
        ]);
    }

    $this->actingAs($this->user)
        ->get('/mantenimientos?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.current_page', 2)
            ->where('records.last_page', 2)
            ->where('records.total', 30)
            ->has('records.data', 5));
});

test('full maintenance details are loaded only from the detail endpoint', function () {
    $maintenance = createMaintenanceForIndex($this->park, [
        'reason' => 'Rama peligrosa',
        'result' => 'Favorable',
        'observations' => 'Detalle reservado para carga diferida.',
    ]);

    $this->actingAs($this->user)
        ->getJson("/mantenimientos/{$maintenance->id}")
        ->assertOk()
        ->assertJsonPath('record.id', $maintenance->id)
        ->assertJsonPath('record.reason', 'Rama peligrosa')
        ->assertJsonPath('record.result', 'Favorable')
        ->assertJsonPath('record.observations', 'Detalle reservado para carga diferida.');
});
