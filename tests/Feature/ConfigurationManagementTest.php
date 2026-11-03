<?php

use App\Models\GreenAreaType;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access configuration', function () {
    auth()->forgetUser();
    $this->get('/configuracion')->assertRedirect('/login');
});

test('the former plants route redirects to configuration', function () {
    $this->actingAs(tenant())
        ->get('/plantas')
        ->assertRedirect('/configuracion');
});

test('configuration renders catalogs rules and institutional data', function () {
    $this->actingAs(tenant())
        ->get('/configuracion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuration/index')
            ->has('greenAreaTypes', 8)
            ->has('maintenanceTypes', 12)
            ->where('institution.organization_name', 'Mi organización'));
});

test('catalog entries can be registered edited and deactivated', function () {
    $this->actingAs(tenant())
        ->post('/configuracion/tipos-area', [
            'name' => 'Vivero',
            'description' => 'Zona de de propagación.',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $areaType = GreenAreaType::query()->where('name', 'Vivero')->firstOrFail();

    $this->put("/configuracion/tipos-area/{$areaType->id}", [
        'name' => 'Vivero central',
        'description' => 'Catálogo actualizado.',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->patch("/configuracion/tipos-area/{$areaType->id}/estado")
        ->assertSessionHasNoErrors();

    expect($areaType->fresh())
        ->name->toBe('Vivero central')
        ->is_active->toBeFalse();
});

test('frequency rules enforce their scheduling validations', function () {
    $user = tenant();

    $this->actingAs($user)
        ->from('/configuracion')
        ->post('/configuracion/reglas', [
            'green_area_type' => 'Parque',
            'maintenance_type' => 'Poda',
            'frequency_days' => 0,
            'advance_days' => -1,
            'is_active' => true,
        ])
        ->assertRedirect('/configuracion')
        ->assertSessionHasErrors(['frequency_days', 'advance_days']);

    $this->from('/configuracion')
        ->post('/configuracion/reglas', [
            'green_area_type' => 'Parque',
            'maintenance_type' => 'Poda',
            'frequency_days' => 5,
            'advance_days' => 6,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('advance_days');

    $this->post('/configuracion/reglas', [
        'green_area_type' => 'Parque',
        'maintenance_type' => 'Poda',
        'frequency_days' => 10,
        'advance_days' => 2,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->from('/configuracion')
        ->post('/configuracion/reglas', [
            'green_area_type' => 'Parque',
            'maintenance_type' => 'Poda',
            'frequency_days' => 15,
            'advance_days' => 3,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('maintenance_type');

    expect(MaintenanceRule::query()->count())->toBe(1);
});

test('only active rules create alerts using the configured advance days', function () {
    $rule = MaintenanceRule::create([
        'green_area_type' => 'Parque',
        'maintenance_type' => 'Riego',
        'frequency_days' => 10,
        'advance_days' => 3,
        'is_active' => true,
    ]);

    $park = Park::create([
        'name' => 'Parque con regla',
        'green_area_type' => 'Parque',
        'registration_date' => '2026-10-14',
    ]);

    $record = $park->maintenanceRecords()->firstOrFail();
    expect($record->scheduled_date->toDateString())->toBe('2026-10-24')
        ->and($record->alert_date->toDateString())->toBe('2026-10-21')
        ->and($record->advance_days)->toBe(3);

    $rule->update(['is_active' => false]);

    $parkWithoutRule = Park::create([
        'name' => 'Parque sin regla activa',
        'green_area_type' => 'Parque',
        'registration_date' => '2026-10-14',
    ]);

    expect($parkWithoutRule->schedules()->count())->toBe(0)
        ->and($parkWithoutRule->maintenanceRecords()->count())->toBe(0);
});

test('renaming catalogs keeps related operational records synchronized', function () {
    $rule = MaintenanceRule::create([
        'green_area_type' => 'Parque',
        'maintenance_type' => 'Poda',
        'frequency_days' => 15,
        'advance_days' => 2,
        'is_active' => true,
    ]);
    $park = Park::create(['name' => 'Parque Central', 'green_area_type' => 'Parque']);
    $areaType = GreenAreaType::query()->where('name', 'Parque')->firstOrFail();
    $maintenanceType = MaintenanceType::query()->where('name', 'Poda')->firstOrFail();

    $this->actingAs(tenant())
        ->put("/configuracion/tipos-area/{$areaType->id}", [
            'name' => 'Parque urbano',
            'description' => $areaType->description,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

    $this->put("/configuracion/tipos-mantenimiento/{$maintenanceType->id}", [
        'name' => 'Poda preventiva',
        'description' => $maintenanceType->description,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($park->fresh()->green_area_type)->toBe('Parque urbano')
        ->and($rule->fresh()->green_area_type)->toBe('Parque urbano')
        ->and($rule->fresh()->maintenance_type)->toBe('Poda preventiva')
        ->and($park->schedules()->firstOrFail()->maintenance_type)->toBe('Poda preventiva')
        ->and($park->maintenanceRecords()->firstOrFail()->maintenance_type)->toBe('Poda preventiva');
});

test('institutional data is saved and exposed to reports', function () {
    $this->actingAs(tenant())
        ->post('/configuracion/datos-institucionales', [
            'organization_name' => 'Organización de Prueba',
            'unit_name' => 'Área de Servicios Públicos',
            'address' => 'Av. Central 123',
            'manager_name' => 'Ana Torres',
            'management_year' => '2027',
            'document_footer' => 'Gestión responsable de las áreas verdes.',
        ])
        ->assertSessionHasNoErrors();

    expect(InstitutionalSetting::query()->firstOrFail())
        ->organization_name->toBe('Organización de Prueba')
        ->manager_name->toBe('Ana Torres');

    $this->get('/reportes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('institutionName', 'Organización de Prueba'));
});
