<?php

use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the workers catalog', function () {
    auth()->forgetUser();
    $this->get('/trabajadores')->assertRedirect('/login');
});

test('authenticated users can view the workers catalog', function () {
    $worker = Worker::create([
        'document_number' => '71234567',
        'first_name' => 'Carlos',
        'last_name' => 'Méndez Torres',
        'position' => 'Jardinero',
        'is_active' => true,
    ]);

    $this->actingAs(tenant())
        ->get('/trabajadores')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workers/index')
            ->where('workers.0.id', $worker->id)
            ->where('workers.0.code', 'TRB-0001')
            ->where('workers.0.full_name', 'Carlos Méndez Torres')
            ->where('nextGeneratedCode', 'TRB-0002'));
});

test('a worker is registered with an automatic code', function () {
    $response = $this->actingAs(tenant())
        ->post('/trabajadores', [
            'code' => 'CODIGO-MANUAL',
            'document_number' => '72889911',
            'first_name' => 'María',
            'last_name' => 'Ramos Pérez',
            'position' => 'Podadora',
            'phone' => '943123456',
            'crew' => 'Cuadrilla de poda',
            'observations' => 'Personal de campo.',
            'is_active' => true,
        ]);

    $response->assertRedirect('/trabajadores');

    $this->assertDatabaseHas('workers', [
        'code' => 'TRB-0001',
        'document_number' => '72889911',
        'first_name' => 'María',
        'is_active' => true,
    ]);
});

test('document number is required and cannot be repeated', function () {
    Worker::create([
        'document_number' => '70001111',
        'first_name' => 'Ana',
        'last_name' => 'Flores',
        'position' => 'Jardinera',
        'is_active' => true,
    ]);

    $this->actingAs(tenant())
        ->from('/trabajadores')
        ->post('/trabajadores', [
            'document_number' => '70001111',
            'first_name' => 'Lucía',
            'last_name' => 'Paredes',
            'position' => 'Supervisora',
            'is_active' => true,
        ])
        ->assertRedirect('/trabajadores')
        ->assertSessionHasErrors('document_number');
});

test('a worker can be updated and deactivated', function () {
    $worker = Worker::create([
        'document_number' => '75553333',
        'first_name' => 'José',
        'last_name' => 'Salas',
        'position' => 'Auxiliar',
        'is_active' => true,
    ]);

    $this->actingAs(tenant())
        ->put("/trabajadores/{$worker->id}", [
            'document_number' => '75553333',
            'first_name' => 'José Luis',
            'last_name' => 'Salas',
            'position' => 'Capataz',
            'phone' => null,
            'crew' => null,
            'observations' => null,
            'is_active' => true,
        ])
        ->assertRedirect('/trabajadores');

    $this->patch("/trabajadores/{$worker->id}/toggle-status")
        ->assertRedirect('/trabajadores');

    expect($worker->fresh())
        ->first_name->toBe('José Luis')
        ->position->toBe('Capataz')
        ->is_active->toBeFalse();
});

test('maintenance records are linked to and listed for their responsible worker', function () {
    $worker = Worker::create([
        'document_number' => '76664444',
        'first_name' => 'Rosa',
        'last_name' => 'Campos',
        'position' => 'Jardinera',
        'is_active' => true,
    ]);
    $park = Park::create(['name' => 'Parque Central']);

    $maintenance = MaintenanceRecord::create([
        'park_id' => $park->id,
        'maintenance_type' => 'Poda',
        'scheduled_date' => now()->toDateString(),
        'execution_date' => now()->toDateString(),
        'responsible_worker' => $worker->full_name,
        'status' => 'finalizado',
        'result' => 'Óptimo',
    ]);

    expect($maintenance->worker_id)->toBe($worker->id);

    $this->actingAs(tenant())
        ->get("/trabajadores/{$worker->id}/mantenimientos")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workers/index')
            ->where('workerSeleccionado.id', $worker->id)
            ->where('workerMantenimientos.0.id', $maintenance->id)
            ->where('workerMantenimientos.0.park_name', 'Parque Central'));
});

test('maintenance execution stores the selected catalog worker by id', function () {
    $worker = Worker::create([
        'document_number' => '74442222',
        'first_name' => 'Elena',
        'last_name' => 'Vega',
        'position' => 'Podadora',
        'is_active' => true,
    ]);
    $park = Park::create(['name' => 'Parque Los Jardines']);
    $maintenance = MaintenanceRecord::create([
        'park_id' => $park->id,
        'maintenance_type' => 'Poda',
        'scheduled_date' => now()->toDateString(),
        'status' => 'pendiente',
    ]);

    $this->actingAs(tenant())
        ->post("/mantenimientos/{$maintenance->id}/atender", [
            'worker_id' => $worker->id,
            'execution_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'work_description' => 'Poda preventiva completada.',
            'result' => 'Óptimo',
            'status' => 'finalizado',
        ])
        ->assertSessionHasNoErrors();

    expect($maintenance->fresh())
        ->worker_id->toBe($worker->id)
        ->responsible_worker->toBe('Elena Vega')
        ->status->toBe('finalizado');
});
