<?php

use App\Models\Park;
use App\Models\User;
use App\Models\InstitutionalSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function organizacionConLogo(): void
{
    Storage::fake('public');
    // JPEG mínimo válido de 1x1 píxel.
    Storage::disk('public')->put('institutional/logo.jpg', base64_decode(
        '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
    ));
    InstitutionalSetting::query()->update(['logo_path' => 'institutional/logo.jpg']);
}

test('el documento del reporte lleva el logo en el encabezado y la tabla con numeración', function () {
    organizacionConLogo();
    Park::create(['name' => 'Parque Central', 'green_area_type' => 'Parque']);

    $html = $this->actingAs(tenant())
        ->get('/reportes/imprimir?type=parks')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('class="logo" src="data:image/jpeg;base64,')
        ->toContain('Mi organización')
        ->toContain('<th>Ítem</th>')
        ->toContain('Total de registros')
        ->toContain('Parque Central');
});

test('el PDF del reporte se genera con el logo incrustado', function () {
    organizacionConLogo();

    $response = $this->actingAs(tenant())
        ->get('/reportes/exportar/pdf?type=parks')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with($response->getContent(), '%PDF-'))->toBeTrue()
        ->and($response->getContent())->toContain('/Subtype /Image');
});
