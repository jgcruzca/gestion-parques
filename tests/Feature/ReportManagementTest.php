<?php

use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createReportFixtures(): array
{
    $worker = Worker::create([
        'document_number' => '70123456',
        'first_name' => 'Rosa',
        'last_name' => 'Campos',
        'position' => 'Jardinera',
        'is_active' => true,
    ]);

    $park = Park::create([
        'name' => 'Parque Central',
        'green_area_type' => 'Parque',
        'address' => 'Av. Principal',
        'zone' => 'Sector Centro',
        'public_use_area' => 250,
        'green_area' => 180,
        'has_water_point' => true,
        'water_supply_type' => 'Red pública',
        'water_point_status' => 'Operativo',
        'water_point_observations' => 'Revisión mensual.',
        'conservation_status' => 'bueno',
        'is_active' => true,
    ]);

    $overdue = MaintenanceRecord::create([
        'park_id' => $park->id,
        'worker_id' => $worker->id,
        'maintenance_type' => 'Poda',
        'scheduled_date' => '2026-10-10',
        'responsible_worker' => $worker->full_name,
        'status' => 'pendiente',
    ]);

    $completed = MaintenanceRecord::create([
        'park_id' => $park->id,
        'worker_id' => $worker->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-12',
        'execution_date' => '2026-10-12',
        'responsible_worker' => $worker->full_name,
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'status' => 'finalizado',
        'work_description' => 'Riego integral realizado.',
        'document_name' => 'parte-riego.pdf',
    ]);

    return compact('worker', 'park', 'overdue', 'completed');
}

test('guests cannot access reports', function () {
    auth()->forgetUser();
    $this->get('/reportes')->assertRedirect('/login');
});

test('the reports center renders all report groups', function () {
    $fixtures = createReportFixtures();

    $this->actingAs(tenant())
        ->get('/reportes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('parksReport.0.name', 'Parque Central')
            ->where('maintenanceReport.0.id', $fixtures['completed']->id)
            ->where('parkHistories.0.maintenance_count', 2)
            ->where('workerReport.0.hours', 2)
            ->where('workerReport.0.completed_count', 1)
            ->where('alertsReport.counts.overdue', 1)
            ->where('waterReport.counts.with_water', 1)
            ->where('waterReport.counts.operational', 1));
});

test('maintenance report filters by period worker and status', function () {
    $fixtures = createReportFixtures();

    $this->actingAs(tenant())
        ->get('/reportes?date_from=2026-10-12&date_to=2026-10-12&worker_id='.$fixtures['worker']->id.'&status=finalizado')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenanceReport', 1)
            ->where('maintenanceReport.0.id', $fixtures['completed']->id)
            ->where('maintenanceReport.0.worker_name', 'Rosa Campos')
            ->where('maintenanceReport.0.duration_label', '2 h'));
});

test('maintenance report combines month type and advanced filters', function () {
    $fixtures = createReportFixtures();

    MaintenanceRecord::create([
        'park_id' => $fixtures['park']->id,
        'maintenance_type' => 'Riego',
        'is_extraordinary' => true,
        'scheduled_date' => '2026-10-20',
        'duration_minutes' => 45,
        'status' => 'pendiente',
    ]);

    $query = http_build_query([
        'month' => '2026-10',
        'maintenance_type' => 'Riego',
        'status' => 'finalizado',
        'date_basis' => 'executed',
        'maintenance_origin' => 'ordinary',
        'assignment' => 'assigned',
        'duration_min' => 100,
        'duration_max' => 130,
    ]);

    $this->actingAs(tenant())
        ->get('/reportes?'.$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenanceReport', 1)
            ->where('maintenanceReport.0.id', $fixtures['completed']->id)
            ->where('filters.month', '2026-10')
            ->where('filters.date_basis', 'executed')
            ->where('filters.maintenance_origin', 'ordinary')
            ->where('filters.assignment', 'assigned'));
});

test('park history uses the same combined analytical filters', function () {
    $fixtures = createReportFixtures();

    $query = http_build_query([
        'type' => 'history',
        'month' => '2026-10',
        'maintenance_type' => 'Poda',
        'status' => 'vencido',
        'park_id' => $fixtures['park']->id,
        'worker_id' => $fixtures['worker']->id,
        'maintenance_origin' => 'ordinary',
        'assignment' => 'assigned',
    ]);

    $this->actingAs(tenant())
        ->get('/reportes?'.$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeReport', 'history')
            ->has('parkHistories', 1)
            ->where('parkHistories.0.id', $fixtures['park']->id)
            ->has('parkHistories.0.records', 1)
            ->where('parkHistories.0.records.0.id', $fixtures['overdue']->id)
            ->where('parkHistories.0.records.0.maintenance_type', 'Poda'));

    $csv = $this->actingAs(tenant())
        ->get('/reportes/exportar/csv?'.$query)
        ->streamedContent();

    expect($csv)->toContain('Poda')->not->toContain('Riego');
});

test('an overdue maintenance requires a reason and exposes it in the audit report', function () {
    $fixtures = createReportFixtures();
    $user = tenant(['name' => 'Supervisora Municipal']);
    $payload = [
        'worker_id' => $fixtures['worker']->id,
        'execution_date' => '2026-10-14',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'work_description' => 'Poda correctiva ejecutada en todo el parque.',
        'result' => 'Óptimo',
        'observations' => 'Trabajo verificado.',
        'status' => 'finalizado',
    ];

    $this->actingAs($user)
        ->post("/mantenimientos/{$fixtures['overdue']->id}/atender", $payload)
        ->assertSessionHasErrors('overdue_reason');

    expect($fixtures['overdue']->fresh()->status)->toBe('pendiente')
        ->and(MaintenanceAudit::count())->toBe(0);

    $reason = 'La cuadrilla estuvo atendiendo una emergencia por caída de árboles.';

    $this->actingAs($user)
        ->post("/mantenimientos/{$fixtures['overdue']->id}/atender", [...$payload, 'overdue_reason' => $reason])
        ->assertSessionHasNoErrors();

    $audit = MaintenanceAudit::firstOrFail();
    expect($audit->reason)->toBe($reason)
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->overdue_days)->toBe(4)
        ->and($audit->metadata['action'])->toBe('atender');

    $query = http_build_query([
        'type' => 'audit',
        'month' => '2026-10',
        'maintenance_type' => 'Poda',
        'status' => 'finalizado',
    ]);

    $this->actingAs($user)
        ->get('/reportes?'.$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeReport', 'audit')
            ->has('auditReport', 1)
            ->where('auditReport.0.reason', $reason)
            ->where('auditReport.0.recorded_by', 'Supervisora Municipal')
            ->where('auditReport.0.overdue_days', 4));

    expect($this->actingAs($user)->get('/reportes/exportar/csv?'.$query)->streamedContent())
        ->toContain($reason)
        ->toContain('Supervisora Municipal');
});

test('an overdue maintenance can be justified with only a supporting document', function () {
    Storage::fake('public');
    $fixtures = createReportFixtures();
    $user = tenant(['name' => 'Supervisora Municipal']);
    $document = UploadedFile::fake()->create('soporte-vencimiento.pdf', 180, 'application/pdf');

    $this->actingAs($user)
        ->post("/mantenimientos/{$fixtures['overdue']->id}/atender", [
            'worker_id' => $fixtures['worker']->id,
            'execution_date' => '2026-10-14',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'work_description' => 'Poda correctiva ejecutada en todo el parque.',
            'result' => 'Óptimo',
            'observations' => 'Trabajo verificado.',
            'status' => 'finalizado',
            'overdue_document' => $document,
        ])
        ->assertSessionHasNoErrors();

    $audit = MaintenanceAudit::firstOrFail();

    expect($audit->reason)->toBe('Se adjuntó un documento de respaldo.')
        ->and($audit->document_name)->toBe('soporte-vencimiento.pdf')
        ->and($audit->document_path)->not->toBeNull()
        ->and($audit->metadata['has_document'])->toBeTrue();

    Storage::disk('public')->assertExists($audit->document_path);

    $query = http_build_query(['type' => 'audit', 'month' => '2026-10']);

    $this->actingAs($user)
        ->get('/reportes?'.$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditReport.0.document_name', 'soporte-vencimiento.pdf')
            ->where('auditReport.0.document_url', "/mantenimientos/auditorias/{$audit->id}/documento"));

    $this->actingAs($user)
        ->get("/mantenimientos/auditorias/{$audit->id}/documento")
        ->assertDownload('soporte-vencimiento.pdf');
});

test('an overdue start can be justified with only a supporting document', function () {
    Storage::fake('public');
    $fixtures = createReportFixtures();
    $user = tenant();
    $document = UploadedFile::fake()->create('soporte-inicio.pdf', 120, 'application/pdf');

    $this->actingAs($user)
        ->post("/mantenimientos/{$fixtures['overdue']->id}/iniciar", [
            'execution_date' => '2026-10-14',
            'start_time' => '08:00',
            'worker_id' => $fixtures['worker']->id,
            'overdue_document' => $document,
        ])
        ->assertSessionHasNoErrors();

    $audit = MaintenanceAudit::firstOrFail();

    expect($audit->metadata['action'])->toBe('iniciar')
        ->and($audit->document_name)->toBe('soporte-inicio.pdf')
        ->and($fixtures['overdue']->fresh()->status)->toBe('en_proceso');
});

test('maintenance report can be exported as excel compatible csv', function () {
    createReportFixtures();

    $response = $this->actingAs(tenant())
        ->get('/reportes/exportar/csv?type=maintenances');

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('Programada')
        ->toContain('Parque Central')
        ->toContain('Rosa Campos');
});

test('general park report can be exported as a real pdf', function () {
    createReportFixtures();

    $response = $this->actingAs(tenant())
        ->get('/reportes/exportar/pdf?type=parks');

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())
        ->toStartWith('%PDF-')
        ->toContain('%%EOF');
});

test('a printable report view is available', function () {
    createReportFixtures();

    $this->actingAs(tenant())
        ->get('/reportes/imprimir?type=workers')
        ->assertOk()
        ->assertSee('Reporte por trabajador')
        ->assertSee('Rosa Campos')
        ->assertSee('Imprimir o guardar como PDF');
});
