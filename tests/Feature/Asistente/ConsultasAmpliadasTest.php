<?php

use App\Models\GreenAreaType;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\ParkSchedule;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;

beforeEach(function () {
    // Miércoles 14/10/2026. La semana actual va del 12/10 al 18/10.
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
    $this->user = tenant();
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

function datosAmpliados(): array
{
    $rosa = Worker::create(['document_number' => '71000001', 'first_name' => 'Rosa', 'last_name' => 'Campos', 'position' => 'Jardinera', 'crew' => 'Norte', 'is_active' => true]);
    $luis = Worker::create(['document_number' => '71000002', 'first_name' => 'Luis', 'last_name' => 'Paredes', 'position' => 'Operario', 'crew' => 'Sur', 'is_active' => false]);
    $ana = Worker::create(['document_number' => '71000003', 'first_name' => 'Ana', 'last_name' => 'Torres', 'position' => 'Jardinera', 'crew' => 'Norte', 'is_active' => true]);

    $mayor = Park::create([
        'name' => 'Plaza Mayor', 'green_area_type' => 'Plaza', 'address' => 'Jirón Lima', 'zone' => 'Centro',
        'is_active' => true, 'has_water_point' => true, 'water_point_status' => 'Operativo',
        'water_supply_type' => 'Red pública', 'green_area' => 500, 'public_use_area' => 800, 'total_area' => 1000,
        'conservation_status' => 'bueno', 'latitude' => -9.12, 'longitude' => -78.51,
        'photo_url' => 'https://example.test/foto.jpg', 'registration_date' => '2026-10-01',
    ]);
    $villa = Park::create([
        'name' => 'Villa Maria', 'green_area_type' => 'Parque', 'address' => 'Av. Villa', 'zone' => 'Sur',
        'is_active' => true, 'has_water_point' => false, 'green_area' => 300, 'conservation_status' => 'regular',
        'registration_date' => '2025-05-10',
    ]);
    $comet = Park::create([
        'name' => 'Comet', 'green_area_type' => 'Parque', 'address' => 'Av. Comet', 'zone' => 'Norte',
        'is_active' => true, 'has_water_point' => true, 'water_point_status' => 'No operativo',
        'water_supply_type' => 'Pozo', 'green_area' => 1200, 'conservation_status' => 'malo',
        'water_point_observations' => 'Bomba averiada', 'registration_date' => '2026-09-20',
    ]);
    $ovalo = Park::create([
        'name' => 'Ovalo Sur', 'green_area_type' => 'Ovalo', 'address' => 'Av. Sur', 'zone' => 'Sur',
        'is_active' => false, 'has_water_point' => false, 'green_area' => 100, 'conservation_status' => 'bueno',
    ]);
    $jardin = Park::create([
        'name' => 'Jardin Norte', 'green_area_type' => 'Jardin', 'address' => 'Calle Norte', 'zone' => 'Norte',
        'is_active' => true, 'has_water_point' => true, 'water_point_status' => 'Operativo',
        'water_supply_type' => 'Red pública', 'green_area' => 50, 'conservation_status' => 'bueno',
    ]);

    foreach (['Riego', 'Poda', 'Limpieza', 'Corte de césped'] as $tipo) {
        MaintenanceType::firstOrCreate(['name' => $tipo], ['is_active' => true]);
    }

    foreach (['Plaza', 'Parque', 'Ovalo', 'Jardin'] as $tipo) {
        GreenAreaType::firstOrCreate(['name' => $tipo], ['is_active' => true]);
    }

    InstitutionalSetting::query()->delete();
    InstitutionalSetting::create([
        'organization_name' => 'Mi organización',
        'unit_name' => 'Área de Parques y Mantenimiento',
        'address' => 'Av. Argentina 100',
        'manager_name' => 'Ana Torres',
        'management_year' => '2026',
        'document_footer' => 'Documento del sistema.',
    ]);

    $registro = fn (array $datos) => MaintenanceRecord::create([
        'responsible_worker' => 'Por asignar',
        'status' => 'pendiente',
        ...$datos,
    ]);

    $datos = [
        'rosa' => $rosa,
        'mayor' => $mayor,
        'villa' => $villa,
        'comet' => $comet,
        'ovalo' => $ovalo,
        'jardin' => $jardin,
        // Realizado ayer.
        'r1' => $registro(['park_id' => $villa->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-10-13', 'execution_date' => '2026-10-13', 'status' => 'finalizado', 'duration_minutes' => 120, 'document_name' => 'acta.pdf', 'document_url' => 'actas/acta.pdf', 'photo_after_url' => 'fotos/despues.jpg']),
        // Realizado el lunes.
        'r2' => $registro(['park_id' => $comet->id, 'worker_id' => $luis->id, 'responsible_worker' => $luis->full_name, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-12', 'execution_date' => '2026-10-12', 'status' => 'finalizado', 'duration_minutes' => 90]),
        // Pendiente para mañana.
        'r3' => $registro(['park_id' => $mayor->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Limpieza', 'scheduled_date' => '2026-10-15']),
        // Vencido de Comet.
        'r4' => $registro(['park_id' => $comet->id, 'worker_id' => $ana->id, 'responsible_worker' => $ana->full_name, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-10-10']),
        // Programado para la próxima semana.
        'r5' => $registro(['park_id' => $ovalo->id, 'maintenance_type' => 'Corte de césped', 'scheduled_date' => '2026-10-20']),
        // Vencido antiguo.
        'r6' => $registro(['park_id' => $jardin->id, 'worker_id' => $luis->id, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-01']),
        // Realizado el mes pasado.
        'r7' => $registro(['park_id' => $mayor->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-09-15', 'execution_date' => '2026-09-15', 'status' => 'finalizado', 'duration_minutes' => 60]),
        // En proceso hoy.
        'r8' => $registro(['park_id' => $villa->id, 'worker_id' => $ana->id, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-14', 'status' => 'en_proceso']),
        // Cancelado.
        'r9' => $registro(['park_id' => $comet->id, 'maintenance_type' => 'Limpieza', 'scheduled_date' => '2026-09-05', 'status' => 'cancelado', 'observations' => 'Cancelado por lluvia']),
        // Futuro lejano.
        'r10' => $registro(['park_id' => $comet->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-11-05']),
        // Adicional realizado la semana pasada.
        'r11' => $registro(['park_id' => $comet->id, 'worker_id' => $ana->id, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-08', 'execution_date' => '2026-10-08', 'status' => 'finalizado', 'duration_minutes' => 45, 'is_extraordinary' => true, 'reason' => 'Árbol caído']),
        // Vencido con justificación y documento.
        'r12' => $registro(['park_id' => $mayor->id, 'maintenance_type' => 'Limpieza', 'scheduled_date' => '2026-10-09']),
    ];

    ParkSchedule::create(['park_id' => $villa->id, 'maintenance_type' => 'Riego', 'frequency_days' => 7, 'last_executed_at' => '2026-10-13', 'next_scheduled_date' => '2026-10-20', 'is_active' => true]);
    ParkSchedule::create(['park_id' => $villa->id, 'maintenance_type' => 'Poda', 'frequency_days' => 30, 'next_scheduled_date' => '2026-11-01', 'is_active' => true]);

    MaintenanceRule::create(['green_area_type' => 'Plaza', 'maintenance_type' => 'Riego', 'frequency_days' => 7, 'advance_days' => 1, 'is_active' => true]);
    MaintenanceRule::create(['green_area_type' => 'Parque', 'maintenance_type' => 'Poda', 'frequency_days' => 30, 'advance_days' => 3, 'is_active' => true]);

    MaintenanceAudit::create([
        'maintenance_record_id' => $datos['r12']->id,
        'user_id' => auth()->id(),
        'event' => 'overdue_justification',
        'reason' => 'Falta de agua en el sector',
        'document_path' => 'maintenance-audits/respaldo.pdf',
        'document_name' => 'respaldo.pdf',
        'scheduled_date' => '2026-10-09',
        'overdue_days' => 5,
        'metadata' => ['action' => 'atender'],
    ]);

    MaintenanceAudit::create([
        'maintenance_record_id' => $datos['r4']->id,
        'user_id' => auth()->id(),
        'event' => 'overdue_justification',
        'reason' => 'Sin personal suficiente',
        'scheduled_date' => '2026-10-10',
        'overdue_days' => 4,
        'metadata' => ['action' => 'iniciar'],
    ]);

    return $datos;
}

function consultarAmpliado($prueba, string $mensaje)
{
    return $prueba->postJson('/asistente/consultar', ['mensaje' => $mensaje]);
}

// ---------------------------------------------------------------- Fechas

test('"falta mañana" incluye los pendientes programados para mañana', function () {
    $datos = datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Qué falta mañana?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    expect(collect($respuesta->json('lista'))->implode(' '))->toContain($datos['r3']->maintenance_number);
});

test('"falta esta semana" incluye los pendientes futuros dentro de la semana', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué falta esta semana?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('"falta ayer" solo devuelve lo programado para ayer que sigue pendiente', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué faltó ayer?')
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonPath('datos.total', 0);
});

test('"vencidos" sigue significando solo lo atrasado', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los vencidos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 3);
});

test('entiende mañana, pasado mañana y la próxima semana', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los mantenimientos de pasado mañana')
        ->assertJsonPath('estado', 'sin_resultados');

    consultarAmpliado($this, 'Dame los mantenimientos de la próxima semana')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los mantenimientos de los últimos 7 días')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 6);
});

test('entiende fechas relativas, días de la semana y años', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame lo realizado hace 6 días')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los mantenimientos del lunes')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los mantenimientos de 2025')
        ->assertJsonPath('estado', 'sin_resultados');
});

// ---------------------------------------------------------------- Tipos y resúmenes

test('filtra por tipo de mantenimiento real del catálogo', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué riegos faltan?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los riegos realizados en octubre')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, '¿Cuántos riegos se hicieron?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);
});

test('un tipo que no existe en el catálogo no devuelve todos los registros', function () {
    datosAmpliados();
    MaintenanceType::query()->where('name', 'Riego')->delete();
    MaintenanceRecord::query()->where('maintenance_type', 'Riego')->update(['maintenance_type' => 'Hidratación']);

    consultarAmpliado($this, 'Dame los riegos de esta semana')
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonPath('mensaje', 'No encontré un tipo de mantenimiento llamado «riegos» en el catálogo del sistema.');
});

test('"qué tipos se hicieron" devuelve un resumen y no un listado de órdenes', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Qué tipos de mantenimiento se hicieron esta semana?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'resumir_mantenimientos')
        ->assertJsonPath('datos.agrupado_por', 'tipo')
        ->assertJsonPath('datos.grupos.Riego', 1)
        ->assertJsonPath('datos.grupos.Poda', 1);

    expect($respuesta->json('mensaje'))->toContain('agrupados por tipo');
});

test('"qué tipos faltan" resume los pendientes por tipo', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué tipos de mantenimiento faltan esta semana?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'resumir_mantenimientos')
        ->assertJsonPath('datos.grupos.Limpieza', 1);
});

test('cuenta mantenimientos por tipo, parque y estado', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Cuántos mantenimientos hay de cada tipo?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 12)
        ->assertJsonPath('datos.grupos.Riego', 4);

    consultarAmpliado($this, 'Dame los mantenimientos agrupados por parque')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.agrupado_por', 'parque')
        ->assertJsonPath('datos.grupos.Comet', 5);

    consultarAmpliado($this, '¿Qué trabajos hay de cada estado?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.agrupado_por', 'estado')
        ->assertJsonPath('datos.grupos.Finalizado', 4);
});

test('detalla una orden por su número con duración y trabajador', function () {
    $datos = datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Muéstrame el mantenimiento '.$datos['r1']->maintenance_number)
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_detalle_mantenimiento')
        ->assertJsonPath('datos.numero', $datos['r1']->maintenance_number);

    $texto = collect($respuesta->json('lista'))->implode(' ');

    expect($texto)->toContain('Villa Maria')
        ->toContain('Rosa Campos')
        ->toContain('2 h')
        ->toContain('Finalizado');
});

test('filtra adicionales, sin responsable, duración y días de atraso', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué mantenimientos son adicionales?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los mantenimientos sin responsable')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 3);

    consultarAmpliado($this, 'Dame los trabajos que duraron más de dos horas')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Dame los vencidos con más de 7 días de atraso')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('busca en las observaciones cuando se pide expresamente', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Busca en las observaciones lluvia')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

// ---------------------------------------------------------------- Parques y agua

test('lista y cuenta los parques con punto de agua', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Dame los parques con punto de agua')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_puntos_agua')
        ->assertJsonCount(3, 'lista');

    expect(collect($respuesta->json('lista'))->implode(' '))->toContain('Comet');

    consultarAmpliado($this, '¿Cuántos parques tienen punto de agua?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 5)
        ->assertJsonPath('datos.con_agua', 3)
        ->assertJsonPath('datos.sin_agua', 2);

    consultarAmpliado($this, '¿Qué parques no tienen punto de agua?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(2, 'lista');
});

test('consulta el agua de un parque concreto', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Villa Maria tiene punto de agua?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_puntos_agua')
        ->assertJsonPath('datos.con_agua', false);

    expect(collect($respuesta->json('lista'))->implode(' '))->toContain('Punto de agua: No');
});

test('filtra por estado del agua y tipo de abastecimiento', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué parques tienen el punto de agua operativo?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(2, 'lista');

    consultarAmpliado($this, 'Dame los parques abastecidos por red pública')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(2, 'lista');
});

test('combina filtros de parques: zona, tipo, estado y agua', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Dame los parques activos de la zona Sur sin punto de agua')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    expect($respuesta->json('lista.0'))->toContain('Villa Maria');

    consultarAmpliado($this, 'Muéstrame las plazas de la zona Centro')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    consultarAmpliado($this, '¿Cuántos parques están en mal estado?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Muéstrame los parques inactivos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');
});

test('consulta superficies, ordena y filtra por tamaño', function () {
    datosAmpliados();

    $detalle = consultarAmpliado($this, '¿Cuántos metros cuadrados de área verde tiene Villa Maria?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_parques');

    expect(collect($detalle->json('lista'))->implode(' '))->toContain('Área verde: 300 m²');

    consultarAmpliado($this, '¿Qué parques tienen más de 600 m² de área verde?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    $grande = consultarAmpliado($this, 'Dame los parques más grandes')
        ->assertJsonPath('estado', 'completado');

    expect($grande->json('lista.0'))->toContain('Comet');
});

test('consulta parques registrados en un período', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Qué parques se registraron en septiembre?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    expect($respuesta->json('lista.0'))->toContain('Comet');
});

// ---------------------------------------------------------------- Trabajadores

test('filtra trabajadores por estado, cargo y cuadrilla', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los trabajadores activos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);

    consultarAmpliado($this, '¿Quiénes son jardineros?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);

    consultarAmpliado($this, 'Dame el personal de la cuadrilla Norte')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);
});

test('consulta las horas y los parques de un trabajador', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Cuántas horas tiene registradas Rosa Campos?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_trabajadores');

    expect(collect($respuesta->json('lista'))->implode(' '))->toContain('3 h')
        ->toContain('Villa Maria');
});

test('filtra mantenimientos por cuadrilla del trabajador', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los pendientes de la cuadrilla Norte')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('filtra los trabajos asignados a una persona en una fecha', function () {
    datosAmpliados();

    consultarAmpliado($this, '¿Qué tiene asignado Rosa para mañana?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

// ---------------------------------------------------------------- Programación y catálogos

test('consulta la frecuencia y la próxima fecha de un parque', function () {
    datosAmpliados();

    $riego = consultarAmpliado($this, '¿Cada cuántos días se riega en Villa Maria?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_programacion');

    expect(collect($riego->json('lista'))->implode(' '))->toContain('Cada 7 días');

    $poda = consultarAmpliado($this, '¿Cuál es la próxima fecha de poda de Villa Maria?')
        ->assertJsonPath('estado', 'completado');

    expect(collect($poda->json('lista'))->implode(' '))->toContain('01/11/2026');
});

test('consulta las reglas de mantenimiento por tipo de área', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, '¿Qué reglas de mantenimiento hay?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_programacion');

    expect(collect($respuesta->json('lista'))->implode(' '))->toContain('Plaza')
        ->toContain('Cada 7 días');
});

test('consulta catálogos e información institucional', function () {
    datosAmpliados();

    $catalogo = consultarAmpliado($this, '¿Qué tipos de mantenimiento están registrados?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_catalogos');

    expect($catalogo->json('lista'))->toContain('Riego · Activo');

    consultarAmpliado($this, '¿Qué tipos de áreas verdes hay?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_catalogos');

    $institucion = consultarAmpliado($this, '¿Quién figura como responsable del área?')
        ->assertJsonPath('estado', 'completado');

    expect(collect($institucion->json('lista'))->implode(' '))->toContain('Ana Torres');
});

// ---------------------------------------------------------------- Justificaciones

test('lista los motivos de retraso y filtra los que tienen documento', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los motivos de retraso')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_justificaciones')
        ->assertJsonPath('datos.total', 2);

    $conDocumento = consultarAmpliado($this, '¿Qué justificaciones tienen documento de respaldo?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    expect($conDocumento->json('lista.0'))->toContain('respaldo.pdf');
});

test('entrega el documento de respaldo de una orden concreta', function () {
    $datos = datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Dame el documento de respaldo de '.$datos['r12']->maintenance_number)
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_justificaciones')
        ->assertJsonPath('archivo.nombre', 'respaldo.pdf');

    expect($respuesta->json('archivo.url'))->toStartWith('/mantenimientos/auditorias/');
});

test('filtra los motivos por parque y busca texto en el motivo', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los motivos de retraso de Plaza Mayor')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Busca en las observaciones agua')
        ->assertJsonPath('estado', 'sin_resultados');
});

// ---------------------------------------------------------------- Continuidad

test('un seguimiento de conteo conserva los filtros de agua', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los parques de la zona Norte con punto de agua')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(2, 'lista');

    consultarAmpliado($this, '¿y cuántos son?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_puntos_agua')
        ->assertJsonPath('datos.total', 2);
});

test('un seguimiento cambia el parque y conserva los vencidos con tipos', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los vencidos de Comet')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'ahora de Plaza Mayor')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('un seguimiento cambia el tipo de trabajo y conserva el período', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los riegos realizados esta semana')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'y las podas')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('un seguimiento puede quitar un filtro sin empezar de cero', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los vencidos de Comet')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'quítale el parque')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 3);
});

test('entiende lenguaje informal con fechas y estados', function () {
    datosAmpliados();

    consultarAmpliado($this, 'q se hizo ayer')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'Qué chambas faltan pa mañana')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    consultarAmpliado($this, 'los atrasados nomás')
        ->assertJsonPath('estado', 'completado');
});

// ---------------------------------------------------------------- Reportes

test('genera el reporte de puntos de agua con sus filtros', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Genera el reporte de parques con punto de agua en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'agua')
        ->assertJsonPath('datos.registros', 3);

    $url = $respuesta->json('archivo.url');

    expect($url)->toStartWith('/reportes/exportar/pdf?')
        ->and($url)->toContain('type=water')
        ->and($url)->toContain('park_water=con');

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('genera el reporte de motivos de retraso y el de historial', function () {
    $datos = datosAmpliados();

    $auditoria = consultarAmpliado($this, 'Genera el reporte de motivos de retraso de octubre en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'justificaciones')
        ->assertJsonPath('datos.registros', 2);

    expect($auditoria->json('archivo.url'))->toContain('type=audit');

    $historial = consultarAmpliado($this, 'Genera el reporte de historial de Villa Maria en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'historial');

    expect($historial->json('archivo.url'))->toContain('type=history')
        ->toContain('park_id='.$datos['villa']->id);
});

test('genera el reporte de alertas y el de trabajadores activos', function () {
    datosAmpliados();

    $alertas = consultarAmpliado($this, 'Genera el reporte de alertas en Excel')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'alertas');

    expect($alertas->json('archivo.url'))->toContain('type=alerts');

    $trabajadores = consultarAmpliado($this, 'Genera el reporte de trabajadores activos en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'trabajadores')
        ->assertJsonPath('datos.registros', 2);

    expect($trabajadores->json('archivo.url'))->toContain('worker_active=1');
});

test('filtra mantenimientos de varios parques a la vez', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los mantenimientos de Comet y Villa Maria')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 7);
});

test('consulta las alertas operativas y los parques al día', function () {
    datosAmpliados();

    $alertas = consultarAmpliado($this, 'Dame las alertas de Comet')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_alertas')
        ->assertJsonPath('datos.vencidos', 1);

    expect(collect($alertas->json('lista'))->implode(' '))->toContain('Comet');

    consultarAmpliado($this, '¿Qué parques están al día?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_alertas')
        ->assertJsonPath('datos.total', 1);
});

test('compara los mantenimientos realizados de dos meses', function () {
    datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Compara los mantenimientos realizados de octubre y septiembre')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'resumir_mantenimientos')
        ->assertJsonPath('datos.comparacion.Octubre 2026', 3)
        ->assertJsonPath('datos.comparacion.Septiembre 2026', 1)
        ->assertJsonPath('datos.diferencia', -2);

    expect($respuesta->json('mensaje'))->toContain('Octubre 2026');
});

test('la exportación de varios parques conserva sus identificadores', function () {
    $datos = datosAmpliados();

    $reporte = consultarAmpliado($this, 'Genera el reporte de mantenimientos de Comet y Villa Maria en PDF')
        ->assertJsonPath('estado', 'completado');

    expect($reporte->json('archivo.url'))
        ->toContain('park_ids='.$datos['comet']->id.'%2C'.$datos['villa']->id);
});

test('la exportación de parques conserva los filtros de la consulta anterior', function () {
    datosAmpliados();

    consultarAmpliado($this, 'Dame los parques activos de la zona Sur sin punto de agua')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    $reporte = consultarAmpliado($this, 'ahora en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'parques')
        ->assertJsonPath('datos.registros', 1);

    $url = $reporte->json('archivo.url');

    expect($url)->toContain('park_zone=Sur')
        ->toContain('park_water=sin');
});

test('un reporte con filtros de fecha y parque coincide con el módulo de reportes', function () {
    $datos = datosAmpliados();

    $respuesta = consultarAmpliado($this, 'Genera el reporte de mantenimientos realizados del 8 al 13 de octubre en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.registros', 3);

    $contenido = $this->get($respuesta->json('archivo.url'))->assertOk()->getContent();

    expect($contenido)->toStartWith('%PDF-');

    $csv = consultarAmpliado($this, 'Genera el reporte de mantenimientos de Comet en Excel')
        ->assertJsonPath('estado', 'completado');

    $texto = $this->get($csv->json('archivo.url'))->streamedContent();

    expect($texto)->toContain('Comet')
        ->and($texto)->toContain($datos['r11']->maintenance_number);
});
