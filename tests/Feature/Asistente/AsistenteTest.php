<?php

use App\Http\Controllers\ReportController;
use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use App\Models\Worker;
use App\Services\Asistente\Asistente;
use App\Services\Asistente\ExtractorParametros;
use App\Services\Asistente\VerificadorReporte;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Miércoles 14/10/2026. La semana del asistente va del 12/10 al 18/10.
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
    $this->user = tenant();
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

function datosAsistente(): array
{
    $rosa = Worker::create(['document_number' => '70123456', 'first_name' => 'Rosa', 'last_name' => 'Campos', 'position' => 'Jardinera', 'is_active' => true]);
    $luis = Worker::create(['document_number' => '70999888', 'first_name' => 'Luis', 'last_name' => 'Paredes', 'position' => 'Operario', 'is_active' => false]);

    $mayor = Park::create(['name' => 'Plaza Mayor', 'green_area_type' => 'Plaza', 'address' => 'Jirón Lima', 'zone' => 'Centro', 'is_active' => true]);
    $norte = Park::create(['name' => 'Plaza Norte', 'green_area_type' => 'Plaza', 'address' => 'Av. Norte', 'zone' => 'Norte', 'is_active' => true]);
    $central = Park::create(['name' => 'Parque Central', 'green_area_type' => 'Parque', 'address' => 'Av. Principal', 'zone' => 'Sector Centro', 'is_active' => false]);

    $registro = fn (array $datos) => MaintenanceRecord::create([
        'responsible_worker' => 'Por asignar',
        'status' => 'pendiente',
        ...$datos,
    ]);

    return [
        'rosa' => $rosa,
        'luis' => $luis,
        'mayor' => $mayor,
        'norte' => $norte,
        'central' => $central,
        // Vencido (pendiente con fecha pasada).
        'vencido' => $registro(['park_id' => $central->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-10']),
        // Realizado esta semana.
        'semana' => $registro(['park_id' => $central->id, 'worker_id' => $rosa->id, 'responsible_worker' => $rosa->full_name, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-10-12', 'execution_date' => '2026-10-12', 'status' => 'finalizado', 'duration_minutes' => 120]),
        // Realizado ayer (13/10).
        'ayer' => $registro(['park_id' => $mayor->id, 'worker_id' => $luis->id, 'responsible_worker' => $luis->full_name, 'maintenance_type' => 'Limpieza', 'scheduled_date' => '2026-10-13', 'execution_date' => '2026-10-13', 'status' => 'finalizado']),
        // Realizado la semana pasada.
        'pasada' => $registro(['park_id' => $mayor->id, 'maintenance_type' => 'Corte de césped', 'scheduled_date' => '2026-10-07', 'execution_date' => '2026-10-07', 'status' => 'finalizado']),
        // Futuro lejano (no aparece como pendiente operativo).
        'futuro' => $registro(['park_id' => $norte->id, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-11-20', 'alert_date' => '2026-11-19']),
        'cancelado' => $registro(['park_id' => $norte->id, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-10-15', 'status' => 'cancelado']),
    ];
}

function preguntar($prueba, string $mensaje)
{
    return $prueba->postJson('/asistente/consultar', ['mensaje' => $mensaje]);
}

// ---------------------------------------------------------------- Reportes

test('CP-01: un reporte incompleto pide los parámetros faltantes y no genera nada', function () {
    datosAsistente();

    preguntar($this, 'Quiero el reporte semanal')
        ->assertOk()
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('parametros_pendientes', ['modulo'])
        ->assertJsonMissingPath('archivo');
});

test('RF-07: las aclaraciones se completan por turnos y solo entonces se genera el reporte', function () {
    datosAsistente();

    preguntar($this, 'Quiero un reporte')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['modulo'])
        ->assertJsonPath('mensaje', '¿De qué módulo necesitas el reporte? Puedo generarlo de mantenimientos, parques, trabajadores, historial por parque, motivos de retraso, alertas o puntos de agua.');

    preguntar($this, 'Mantenimientos')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['periodo', 'formato']);

    preguntar($this, 'esta semana')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['formato']);

    preguntar($this, 'PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_actual')
        ->assertJsonPath('datos.formato', 'pdf');
});

test('CP-02: genera el PDF de mantenimientos de la semana con el período correcto', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'Genera reporte de mantenimientos de esta semana en PDF')
        ->assertOk()
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_actual')
        ->assertJsonPath('datos.desde', '2026-10-12')
        ->assertJsonPath('datos.hasta', '2026-10-18')
        ->assertJsonPath('datos.formato', 'pdf');

    $url = $respuesta->json('archivo.url');

    expect($url)->toStartWith('/reportes/exportar/pdf?')
        ->and($url)->toContain('date_from=2026-10-12')
        ->and($url)->toContain('date_to=2026-10-18')
        ->and($respuesta->json('archivo.nombre'))->toEndWith('.pdf');

    $descarga = $this->get($url)->assertOk();
    expect($descarga->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($descarga->getContent())->toStartWith('%PDF-');
});

test('CP-03: las redacciones equivalentes producen la misma intención, parámetros y enlace', function () {
    datosAsistente();

    $mensajes = [
        'Genera el reporte semanal de mantenimientos en PDF',
        'Quiero el informe de mantenimientos de esta semana en PDF',
        'Sácame el informe de mantenimientos de esta semana en PDF',
        'Necesito el reporte semanal de mantenimiento en pdf',
    ];

    $respuestas = array_map(fn (string $mensaje) => preguntar($this, $mensaje)->assertOk()->json(), $mensajes);

    foreach ($respuestas as $respuesta) {
        expect($respuesta['estado'])->toBe('completado')
            ->and($respuesta['intencion'])->toBe('generar_reporte')
            ->and($respuesta['datos'])->toBe($respuestas[0]['datos'])
            ->and($respuesta['archivo']['url'])->toBe($respuestas[0]['archivo']['url']);
    }
});

test('el reporte del asistente coincide con el del módulo de reportes con filtros equivalentes', function () {
    $datos = datosAsistente();

    // Mantenimientos realizados esta semana (Excel = CSV): el asistente frente al procedimiento manual.
    $asistente = preguntar($this, 'Genera el reporte de mantenimientos realizados esta semana en Excel')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.registros', 2);

    $manual = $this->get('/reportes/exportar/csv?type=maintenances&date_from=2026-10-12&date_to=2026-10-18&status=finalizado&date_basis=executed');
    $delChat = $this->get($asistente->json('archivo.url'));

    $contenido = $delChat->streamedContent();

    expect($contenido)->toBe($manual->streamedContent())
        ->and($contenido)->toContain($datos['semana']->maintenance_number)
        ->and($contenido)->toContain($datos['ayer']->maintenance_number)
        ->and($contenido)->not->toContain($datos['pasada']->maintenance_number)
        ->and($contenido)->not->toContain($datos['vencido']->maintenance_number)
        ->and($delChat->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');

    // Sin estado: todos los mantenimientos de la semana, igual que el reporte manual por defecto.
    $semana = preguntar($this, 'Genera el reporte de mantenimientos de esta semana en Excel')->assertJsonPath('estado', 'completado');

    expect($this->get($semana->json('archivo.url'))->streamedContent())
        ->toBe($this->get('/reportes/exportar/csv?type=maintenances&date_from=2026-10-12&date_to=2026-10-18')->streamedContent());
});

test('un reporte de parques usa el listado completo y avisa que no admite período', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'Reporte de parques en PDF de esta semana')->assertJsonPath('estado', 'completado');

    expect($respuesta->json('archivo.url'))->toStartWith('/reportes/exportar/pdf?type=parks')
        ->and($respuesta->json('mensaje'))->toContain('no admite período')
        ->and($respuesta->json('datos.registros'))->toBe(3);
});

test('"Excel" se entrega como CSV compatible y así se informa', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'Reporte de trabajadores en Excel')->assertJsonPath('estado', 'completado');

    expect($respuesta->json('archivo.url'))->toStartWith('/reportes/exportar/csv?type=workers')
        ->and($respuesta->json('mensaje'))->toContain('CSV compatible con Excel');
});

test('CP-08: sin módulo inequívoco solicita el módulo', function () {
    datosAsistente();

    preguntar($this, 'Genera reporte de ayer en Excel')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['modulo']);
});

test('un reporte de un módulo sin reportes informa la limitación', function () {
    preguntar($this, 'Genera el reporte del dashboard en PDF')
        ->assertJsonPath('estado', 'no_reconocido')
        ->assertJsonMissingPath('archivo');
});

test('"pendientes" en un reporte pide precisar el estado porque el módulo no lo admite como uno solo', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos pendientes de esta semana en PDF')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['estado']);
});

test('CP-09: una fecha inválida pide corrección', function () {
    preguntar($this, 'Quiero el reporte de mantenimientos del 31/02/2026 en PDF')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['fecha'])
        ->assertJsonMissingPath('archivo');

    MaintenanceRecord::create([
        'park_id' => Park::create(['name' => 'Plaza Sur', 'green_area_type' => 'Plaza', 'is_active' => true])->id,
        'maintenance_type' => 'Riego',
        'scheduled_date' => '2026-10-14',
        'execution_date' => '2026-10-14',
        'status' => 'finalizado',
    ]);

    preguntar($this, '14/10/2026')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.desde', '2026-10-14')
        ->assertJsonPath('datos.registros', 1);
});

test('CP-12: una consulta válida sin registros responde sin resultados', function () {
    preguntar($this, 'Muéstrame los mantenimientos pendientes')
        ->assertOk()
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonPath('datos.total', 0)
        ->assertJsonMissingPath('lista');

    preguntar($this, 'Genera el reporte de mantenimientos de esta semana en PDF')
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonMissingPath('archivo');
});

test('CP-16: si el generador de PDF falla no se entrega ningún enlace', function () {
    datosAsistente();

    $this->mock(VerificadorReporte::class, fn ($mock) => $mock->shouldReceive('verificar')->andThrow(new RuntimeException('dompdf')));

    $respuesta = preguntar($this, 'Genera el reporte de mantenimientos de esta semana en PDF')
        ->assertOk()
        ->assertJsonPath('estado', 'error')
        ->assertJsonMissingPath('archivo');

    expect($respuesta->json('mensaje'))->not->toContain('dompdf');
});

test('el verificador genera de verdad el documento con el controlador de reportes', function () {
    datosAsistente();

    app(VerificadorReporte::class)->verificar('pdf', ['type' => 'maintenances']);
    app(VerificadorReporte::class)->verificar('csv', ['type' => 'parks']);

    expect(fn () => app(VerificadorReporte::class)->verificar('pdf', ['type' => 'inexistente']))->toThrow(Exception::class);
});

// ---------------------------------------------------------------- Consultas

test('CP-04: cuenta los parques con datos reales', function () {
    datosAsistente();

    preguntar($this, '¿Cuántos parques tenemos?')
        ->assertOk()
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'contar_parques')
        ->assertJsonPath('datos.total', 3)
        ->assertJsonPath('datos.activos', 2)
        ->assertJsonPath('datos.inactivos', 1);
});

test('CP-05: lista los mantenimientos pendientes de atención con su desglose', function () {
    $datos = datosAsistente();

    $respuesta = preguntar($this, 'Muéstrame mantenimientos pendientes')
        ->assertOk()
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos_pendientes')
        ->assertJsonPath('datos.total', 1)
        ->assertJsonPath('datos.vencidos', 1)
        ->assertJsonPath('datos.para_hoy', 0);

    expect($respuesta->json('lista'))->toHaveCount(1)
        ->and($respuesta->json('lista.0'))->toContain($datos['vencido']->maintenance_number)
        ->and($respuesta->json('lista.0'))->toContain('Parque Central');
});

test('cuenta mantenimientos por estado con el mismo criterio que la bandeja', function () {
    datosAsistente();

    preguntar($this, '¿Cuántos mantenimientos vencidos hay?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1)
        ->assertJsonMissingPath('lista');
});

test('lista los mantenimientos realizados ayer', function () {
    $datos = datosAsistente();

    $respuesta = preguntar($this, '¿Qué mantenimientos se realizaron ayer?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    expect($respuesta->json('lista.0'))->toContain($datos['ayer']->maintenance_number)
        ->and($respuesta->json('lista.0'))->toContain('Realizado 13/10/2026');
});

test('CP-06: cuenta los trabajadores con datos reales', function () {
    datosAsistente();

    preguntar($this, '¿Cuántos trabajadores hay?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'contar_trabajadores')
        ->assertJsonPath('datos.total', 2)
        ->assertJsonPath('datos.activos', 1);
});

test('lista trabajadores y las actividades de un trabajador', function () {
    $datos = datosAsistente();

    preguntar($this, 'Muéstrame los trabajadores')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(2, 'lista');

    $actividades = preguntar($this, 'Actividades de Rosa Campos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_actividades')
        ->assertJsonPath('datos.total', 2);

    expect(collect($actividades->json('lista'))->implode(' '))
        ->toContain($datos['vencido']->maintenance_number)
        ->toContain($datos['semana']->maintenance_number)
        ->not->toContain($datos['ayer']->maintenance_number);
});

test('las actividades sin trabajador ni parque piden aclaración y un nombre inexistente no inventa datos', function () {
    datosAsistente();

    preguntar($this, 'Muéstrame las actividades')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['trabajador']);

    preguntar($this, 'Muéstrame las actividades de Zulema')
        ->assertJsonPath('estado', 'sin_resultados');
});

test('lista y busca parques', function () {
    datosAsistente();

    preguntar($this, 'Muéstrame los parques')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(3, 'lista');

    preguntar($this, 'Busca el parque Plaza Norte')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    preguntar($this, 'Busca el parque Atlantis')
        ->assertJsonPath('estado', 'sin_resultados');
});

test('resume el dashboard con los mismos estados operativos', function () {
    datosAsistente();

    preguntar($this, 'Dame el resumen del dashboard')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.parques_total', 3)
        ->assertJsonPath('datos.parques_activos', 2)
        ->assertJsonPath('datos.mantenimientos_vencidos', 1)
        ->assertJsonPath('datos.mantenimientos_ejecutados_mes', 3);
});

test('la ayuda lista ejemplos', function () {
    preguntar($this, 'ayuda')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'solicitar_ayuda')
        ->assertJsonStructure(['lista']);
});

// ---------------------------------------------------------------- Navegación

test('CP-07: la navegación devuelve una ruta registrada', function (string $mensaje, string $ruta) {
    preguntar($this, $mensaje)
        ->assertOk()
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'navegar_modulo')
        ->assertJsonPath('navegacion.ruta', $ruta);
})->with([
    ['Llévame a parques', '/parques'],
    ['Llévame a gestión de parques', '/parques'],
    ['Abre mantenimientos', '/mantenimientos'],
    ['Ir a trabajadores', '/trabajadores'],
    ['Quiero ir a reportes', '/reportes'],
    ['Ve a configuración', '/configuracion'],
    ['Llévame al dashboard', '/dashboard'],
]);

test('la navegación nunca devuelve direcciones escritas por el usuario', function () {
    $respuesta = preguntar($this, 'Llévame a https://sitio-malicioso.example/robar')->assertOk();

    expect($respuesta->json('navegacion'))->toBeNull()
        ->and($respuesta->json('estado'))->toBe('requiere_aclaracion');
});

// ---------------------------------------------------------------- Rechazos

test('CP-10: una función no disponible se informa', function () {
    preguntar($this, 'Haz una predicción de plagas')
        ->assertOk()
        ->assertJsonPath('estado', 'no_reconocido')
        ->assertJsonPath('intencion', null);
});

test('CP-11: eliminar registros se rechaza y no modifica nada', function () {
    datosAsistente();

    $antes = [Park::count(), Worker::count(), MaintenanceRecord::count(), MaintenanceAudit::count()];

    foreach (['Elimina todos los parques', 'Borra los trabajadores', 'Crea un parque nuevo', 'Edita el mantenimiento 1'] as $mensaje) {
        preguntar($this, $mensaje)->assertOk()->assertJsonPath('estado', 'no_autorizado');
    }

    expect([Park::count(), Worker::count(), MaintenanceRecord::count(), MaintenanceAudit::count()])->toBe($antes);
});

test('las consultas y reportes no modifican la base de datos', function () {
    datosAsistente();

    $antes = [Park::count(), Worker::count(), MaintenanceRecord::count(), MaintenanceAudit::count()];
    $estados = MaintenanceRecord::orderBy('id')->pluck('status')->all();

    foreach ([
        'Dame el resumen del dashboard', '¿Cuántos parques tenemos?', 'Muéstrame mantenimientos pendientes',
        'Actividades de Rosa Campos', 'Genera el reporte de mantenimientos de esta semana en PDF', 'Llévame a parques',
    ] as $mensaje) {
        preguntar($this, $mensaje)->assertOk();
    }

    expect([Park::count(), Worker::count(), MaintenanceRecord::count(), MaintenanceAudit::count()])->toBe($antes)
        ->and(MaintenanceRecord::orderBy('id')->pluck('status')->all())->toBe($estados);
});

test('RF-08: una solicitud no reconocida usa el mensaje definido y no elige una intención', function (string $mensaje) {
    preguntar($this, $mensaje)
        ->assertOk()
        ->assertJsonPath('estado', 'no_reconocido')
        ->assertJsonPath('intencion', null)
        ->assertJsonPath('mensaje', 'No pude identificar tu solicitud. Puedes pedirme información sobre parques, trabajadores, mantenimientos o reportes.');
})->with(['hola', 'cuéntame un chiste', 'qué hora es']);

// ---------------------------------------------------------------- Ambigüedad

test('CP-18: un nombre de parque ambiguo solicita la selección y no ejecuta', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'Mantenimientos de plaza')
        ->assertOk()
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['parque'])
        ->assertJsonPath('opciones', ['Plaza Mayor', 'Plaza Norte'])
        ->assertJsonMissingPath('lista');

    expect($respuesta->json('mensaje'))->toContain('1) Plaza Mayor')->toContain('2) Plaza Norte');

    preguntar($this, '1')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);
});

test('un parque con nombre único se reconoce sin aclaración', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'Mantenimientos del parque Plaza Mayor')
        ->assertJsonPath('estado', 'completado');

    expect($respuesta->json('mensaje'))->toContain('Plaza Mayor');
});

test('cancelar descarta la aclaración pendiente', function () {
    preguntar($this, 'Quiero un reporte')->assertJsonPath('estado', 'requiere_aclaracion');

    preguntar($this, 'olvídalo')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('mensaje', 'Listo, cancelé la solicitud pendiente.');

    preguntar($this, 'PDF')->assertJsonPath('estado', 'no_reconocido');
});

test('una respuesta ilegible a una aclaración la mantiene pendiente', function () {
    preguntar($this, 'Quiero un reporte')->assertJsonPath('estado', 'requiere_aclaracion');

    preguntar($this, 'xyzzy')->assertJsonPath('estado', 'no_reconocido');

    preguntar($this, 'trabajadores')->assertJsonPath('parametros_pendientes', ['formato']);
});

test('una solicitud nueva reemplaza la aclaración pendiente sin mezclar parámetros', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos de esta semana')->assertJsonPath('parametros_pendientes', ['formato']);

    preguntar($this, '¿Cuántos trabajadores hay?')->assertJsonPath('intencion', 'contar_trabajadores');

    // "PDF" ya no completa el reporte de mantenimientos: continúa el tema nuevo (trabajadores).
    preguntar($this, 'PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'trabajadores')
        ->assertJsonPath('datos.formato', 'pdf')
        ->assertJsonMissingPath('datos.periodo');
});

// ---------------------------------------------------------------- Entrada y seguridad

test('CP-13: sin sesión no hay acceso al asistente', function () {
    auth()->logout();
    app('auth')->forgetGuards();

    $this->postJson('/asistente/consultar', ['mensaje' => 'Genera el reporte de mantenimientos en PDF'])->assertUnauthorized();
});

test('CP-14: una solicitud vacía o demasiado larga devuelve un error de validación', function () {
    $this->postJson('/asistente/consultar', ['mensaje' => ''])
        ->assertStatus(422)
        ->assertJsonPath('estado', 'error');

    $this->postJson('/asistente/consultar', ['mensaje' => '   '])->assertStatus(422);
    $this->postJson('/asistente/consultar', [])->assertStatus(422);
    $this->postJson('/asistente/consultar', ['mensaje' => ['a']])->assertStatus(422);

    $this->postJson('/asistente/consultar', ['mensaje' => str_repeat('a', 501)])
        ->assertStatus(422)
        ->assertJsonPath('estado', 'error');

    $this->postJson('/asistente/consultar', ['mensaje' => str_repeat('a', 500)])->assertOk();
});

test('CP-15: una solicitud duplicada durante el procesamiento no se ejecuta', function () {
    datosAsistente();

    $bloqueo = Cache::lock('asistente:procesando:'.$this->user->id, 20);
    $bloqueo->get();

    preguntar($this, '¿Cuántos parques tenemos?')
        ->assertStatus(409)
        ->assertJsonPath('estado', 'error');

    $bloqueo->release();

    preguntar($this, '¿Cuántos parques tenemos?')->assertOk()->assertJsonPath('estado', 'completado');
});

test('CP-19: el texto con etiquetas HTML no se refleja ni se interpreta', function () {
    $respuesta = preguntar($this, '<script>alert("x")</script><img src=x onerror=alert(1)>')
        ->assertOk()
        ->assertJsonPath('estado', 'no_reconocido');

    expect($respuesta->getContent())->not->toContain('<script')->not->toContain('onerror');
});

test('CP-20: una falla del asistente no afecta a los módulos existentes', function () {
    $this->mock(Asistente::class, fn ($mock) => $mock->shouldReceive('procesar')->andThrow(new RuntimeException('falla interna con datos sensibles')));

    $respuesta = preguntar($this, '¿Cuántos parques tenemos?')
        ->assertStatus(500)
        ->assertJsonPath('estado', 'error');

    expect($respuesta->getContent())->not->toContain('falla interna')->not->toContain('RuntimeException');

    foreach (['/dashboard', '/parques', '/mantenimientos', '/trabajadores', '/reportes', '/configuracion'] as $ruta) {
        $this->get($ruta)->assertOk();
    }

    $this->get('/reportes/exportar/pdf?type=parks')->assertOk();
});

test('el asistente limita la frecuencia de solicitudes', function () {
    config(['asistente.limite_por_minuto' => 3]);

    foreach (range(1, 3) as $_) {
        preguntar($this, '¿Cuántos parques tenemos?')->assertOk();
    }

    preguntar($this, '¿Cuántos parques tenemos?')->assertStatus(429);
});

test('el asistente solo expone una ruta y es de tipo POST', function () {
    $rutas = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($ruta) => str_contains($ruta->uri(), 'asistente'));

    expect($rutas)->toHaveCount(1)
        ->and($rutas->first()->methods())->toBe(['POST'])
        ->and($rutas->first()->gatherMiddleware())->toContain('auth')->toContain('verified');

    $this->get('/asistente/consultar')->assertStatus(405);
});

// ---------------------------------------------------------------- Fechas y rendimiento

test('CP-17: la semana pasada y el mes pasado se calculan correctamente al cambiar de mes y de año', function () {
    $extractor = app(ExtractorParametros::class);

    // Miércoles 4/11/2026: la semana pasada fue del 26/10 al 1/11.
    expect($extractor->calcularRango('semana_pasada', Carbon::parse('2026-11-04 12:00', 'America/Lima')))
        ->toBe(['desde' => '2026-10-26', 'hasta' => '2026-11-01'])
        ->and($extractor->calcularRango('semana_actual', Carbon::parse('2026-11-04 12:00', 'America/Lima')))
        ->toBe(['desde' => '2026-11-02', 'hasta' => '2026-11-08']);

    // Cambio de año: el lunes 5/1/2026, la semana pasada empezó el 29/12/2025.
    expect($extractor->calcularRango('semana_pasada', Carbon::parse('2026-01-07 09:00', 'America/Lima')))
        ->toBe(['desde' => '2025-12-29', 'hasta' => '2026-01-04'])
        ->and($extractor->calcularRango('mes_pasado', Carbon::parse('2026-01-15', 'America/Lima')))
        ->toBe(['desde' => '2025-12-01', 'hasta' => '2025-12-31'])
        ->and($extractor->calcularRango('mes_pasado', Carbon::parse('2026-03-31', 'America/Lima')))
        ->toBe(['desde' => '2026-02-01', 'hasta' => '2026-02-28']);
});

test('la semana empieza el lunes y el domingo todavía pertenece a la semana en curso', function () {
    $extractor = app(ExtractorParametros::class);

    expect($extractor->calcularRango('semana_actual', Carbon::parse('2026-10-12 00:00', 'America/Lima')))
        ->toBe(['desde' => '2026-10-12', 'hasta' => '2026-10-18'])
        ->and($extractor->calcularRango('semana_actual', Carbon::parse('2026-10-18 23:59', 'America/Lima')))
        ->toBe(['desde' => '2026-10-12', 'hasta' => '2026-10-18']);
});

test('hoy y ayer se calculan en la zona horaria del asistente y no en UTC', function () {
    $extractor = app(ExtractorParametros::class);

    // 03:00 UTC del 15/10 son las 22:00 del 14/10 en Lima.
    $instante = Carbon::parse('2026-10-15 03:00:00', 'UTC');

    expect($extractor->calcularRango('hoy', $instante))->toBe(['desde' => '2026-10-14', 'hasta' => '2026-10-14'])
        ->and($extractor->calcularRango('ayer', $instante))->toBe(['desde' => '2026-10-13', 'hasta' => '2026-10-13']);
});

test('extrae fechas explícitas válidas, rangos, meses y marca las inválidas', function () {
    $extractor = app(ExtractorParametros::class);

    expect($extractor->extraer('reporte del 14/10/2026', 'generar_reporte'))->toMatchArray(['periodo' => 'fecha', 'desde' => '2026-10-14', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte del 2026-10-05 al 2026-10-09', 'generar_reporte'))->toMatchArray(['periodo' => 'rango', 'desde' => '2026-10-05', 'hasta' => '2026-10-09'])
        ->and($extractor->extraer('reporte del 14 de octubre', 'generar_reporte'))->toMatchArray(['periodo' => 'fecha', 'desde' => '2026-10-14'])
        ->and($extractor->extraer('reporte de septiembre', 'generar_reporte'))->toMatchArray(['periodo' => 'mes_nombre', 'desde' => '2026-09-01', 'hasta' => '2026-09-30'])
        ->and($extractor->extraer('reporte del 31/02/2026', 'generar_reporte'))->toMatchArray(['fecha_invalida' => true])
        ->and($extractor->extraer('reporte del 45 de octubre', 'generar_reporte'))->toMatchArray(['fecha_invalida' => true])
        ->and($extractor->extraer('reporte de ayer', 'generar_reporte'))->not->toHaveKey('fecha_invalida');
});

test('extrae estado, formato y sinónimos de "realizado"', function () {
    $extractor = app(ExtractorParametros::class);

    foreach (['realizados', 'ejecutados', 'terminados', 'completados', 'finalizados', 'hechos'] as $palabra) {
        expect($extractor->extraer("mantenimientos {$palabra} ayer", 'consultar_mantenimientos'))->toMatchArray(['estado' => 'finalizado']);
    }

    expect($extractor->extraer('mantenimientos vencidos', 'consultar_mantenimientos')['estado'])->toBe('vencido')
        ->and($extractor->extraer('reporte en pdf', 'generar_reporte')['formato'])->toBe('pdf')
        ->and($extractor->extraer('reporte en excel', 'generar_reporte')['formato'])->toBe('excel')
        ->and($extractor->extraer('reporte en csv', 'generar_reporte')['formato'])->toBe('excel');
});

test('las solicitudes sin acceso a reportes se procesan en menos de un segundo', function () {
    datosAsistente();

    foreach (['¿Cuántos parques tenemos?', 'Muéstrame los mantenimientos pendientes', 'Mantenimientos del parque Plaza Mayor', 'Dame el resumen del dashboard'] as $mensaje) {
        $inicio = microtime(true);
        preguntar($this, $mensaje)->assertOk();

        expect(microtime(true) - $inicio)->toBeLessThan(1.0);
    }
});

test('el reporte manual no cambió: el controlador de reportes sigue exponiendo sus exportaciones', function () {
    datosAsistente();

    expect(app(ReportController::class))->toBeInstanceOf(ReportController::class);

    $this->get('/reportes/exportar/csv?type=maintenances')->assertOk();
    $this->get('/reportes/exportar/pdf?type=maintenances')->assertOk();
    $this->get('/reportes/imprimir?type=maintenances')->assertOk();
});

test('los ejemplos del manual de uso funcionan', function () {
    $datos = datosAsistente();

    preguntar($this, 'Busca PRQ-'.substr($datos['norte']->code, 4))
        ->assertJsonPath('estado', 'completado')
        ->assertJsonCount(1, 'lista');

    // El mantenimiento vencido está programado el 10/10, dentro de la semana pasada (5 al 11/10).
    preguntar($this, 'Mantenimientos vencidos de esta semana')
        ->assertJsonPath('estado', 'sin_resultados');

    preguntar($this, 'Mantenimientos vencidos de la semana pasada')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'Reporte de parques en Excel')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.registros', 3);

    preguntar($this, 'Genera el reporte de mantenimientos del 05/10/2026 al 09/10/2026 en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.desde', '2026-10-05')
        ->assertJsonPath('datos.hasta', '2026-10-09')
        ->assertJsonPath('datos.registros', 1);
});

test('una conversación nueva descarta la aclaración pendiente del día anterior', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos de esta semana')
        ->assertJsonPath('parametros_pendientes', ['formato']);

    // Tras la medianoche el chat avisa con "reiniciar": "PDF" ya no completa la solicitud anterior.
    $this->postJson('/asistente/consultar', ['mensaje' => 'PDF', 'reiniciar' => true])
        ->assertOk()
        ->assertJsonPath('estado', 'no_reconocido')
        ->assertJsonMissingPath('archivo');

    // Y la nueva conversación funciona con normalidad.
    $this->postJson('/asistente/consultar', ['mensaje' => '¿Cuántos parques tenemos?', 'reiniciar' => true])
        ->assertOk()
        ->assertJsonPath('estado', 'completado');
});

test('sin la indicación de reinicio la aclaración pendiente se conserva', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos de esta semana')->assertJsonPath('parametros_pendientes', ['formato']);

    preguntar($this, 'PDF')->assertJsonPath('estado', 'completado');
});

test('la indicación de reinicio debe ser un valor booleano', function () {
    $this->postJson('/asistente/consultar', ['mensaje' => 'ayuda', 'reiniciar' => 'quizás'])
        ->assertStatus(422)
        ->assertJsonPath('estado', 'error');

    $this->postJson('/asistente/consultar', ['mensaje' => 'ayuda', 'reiniciar' => false])->assertOk();
});

// ---------------------------------------------------------------- Hilo conversacional

test('un seguimiento conserva el parque y cambia el estado', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);

    preguntar($this, 'y los vencidos')
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonPath('datos.total', 0);
});

test('un seguimiento conserva el parque y cambia el período', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('datos.total', 2);

    $respuesta = preguntar($this, 'y los de la semana pasada')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    expect($respuesta->json('mensaje'))->toContain('Plaza Mayor');
});

test('un seguimiento con "sus" hereda el parque buscado', function () {
    datosAsistente();

    preguntar($this, 'Busca el parque Plaza Mayor')->assertJsonPath('estado', 'completado');

    $respuesta = preguntar($this, '¿y sus mantenimientos?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);

    expect($respuesta->json('mensaje'))->toContain('Plaza Mayor');
});

test('un seguimiento de formato reutiliza el reporte anterior', function () {
    datosAsistente();

    $pdf = preguntar($this, 'Genera el reporte de mantenimientos de esta semana en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_actual');

    expect($pdf->json('archivo.nombre'))->toEndWith('.pdf');

    $excel = preguntar($this, 'ahora en Excel')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_actual')
        ->assertJsonPath('datos.formato', 'excel');

    expect($excel->json('archivo.nombre'))->toEndWith('.csv');
});

test('un tema nuevo no hereda los filtros del anterior', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('datos.total', 2);

    preguntar($this, 'Muéstrame los trabajadores')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 2);
});

test('un seguimiento de conteo conserva el tema', function () {
    datosAsistente();

    preguntar($this, 'Muéstrame los parques')->assertJsonPath('estado', 'completado');

    preguntar($this, '¿y cuántos son?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'contar_parques')
        ->assertJsonPath('datos.total', 3);
});

test('una conversación nueva olvida el hilo anterior', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('datos.total', 2);

    $this->postJson('/asistente/consultar', ['mensaje' => 'y los de la semana pasada', 'reiniciar' => true])
        ->assertOk()
        ->assertJsonPath('estado', 'no_reconocido');

    preguntar($this, 'Mantenimientos de Plaza Mayor');
    preguntar($this, 'y los de la semana pasada')->assertJsonPath('estado', 'completado');
});

test('cancelar olvida el tema anterior', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('datos.total', 2);

    preguntar($this, 'olvídalo')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('mensaje', 'Listo, olvidé el tema anterior.');

    preguntar($this, 'y los de la semana pasada')->assertJsonPath('estado', 'no_reconocido');
});

test('un seguimiento de período actualiza el reporte anterior', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos de esta semana en PDF')
        ->assertJsonPath('estado', 'completado');

    $respuesta = preguntar($this, 'y los de la semana pasada')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_pasada')
        ->assertJsonPath('datos.desde', '2026-10-05')
        ->assertJsonPath('datos.hasta', '2026-10-11');

    expect($respuesta->json('archivo.nombre'))->toEndWith('.pdf');
});

test('un formato suelto continúa el tema y pregunta lo que falta', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('datos.total', 2);

    preguntar($this, 'en PDF')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('parametros_pendientes', ['periodo']);

    preguntar($this, 'esta semana')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_actual');
});

test('un seguimiento de reporte cambia el módulo y conserva el formato', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de parques en PDF')->assertJsonPath('estado', 'completado');

    $respuesta = preguntar($this, 'y el de trabajadores')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'trabajadores')
        ->assertJsonPath('datos.formato', 'pdf');

    expect($respuesta->json('archivo.nombre'))->toEndWith('.pdf');
});

test('un seguimiento puede cambiar el parque sin repetir el verbo', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('estado', 'completado');

    $respuesta = preguntar($this, 'y los de Plaza Norte')
        ->assertJsonPath('estado', 'completado');

    expect($respuesta->json('mensaje'))->toContain('Plaza Norte');
});

test('un seguimiento que menciona otro parque conserva los vencidos', function () {
    datosAsistente();

    $villa = Park::create(['name' => 'Villa Maria', 'green_area_type' => 'Parque', 'address' => 'Av. Villa', 'zone' => 'Sur', 'is_active' => true]);
    $comet = Park::create(['name' => 'Comet', 'green_area_type' => 'Parque', 'address' => 'Av. Comet', 'zone' => 'Norte', 'is_active' => true]);

    $registro = fn (array $datos) => MaintenanceRecord::create([
        'responsible_worker' => 'Por asignar',
        'status' => 'pendiente',
        ...$datos,
    ]);

    $registro(['park_id' => $villa->id, 'maintenance_type' => 'Poda', 'scheduled_date' => '2026-10-08']);
    $registro(['park_id' => $comet->id, 'maintenance_type' => 'Riego', 'scheduled_date' => '2026-10-09']);
    $registro(['park_id' => $comet->id, 'maintenance_type' => 'Limpieza', 'scheduled_date' => '2026-10-01', 'execution_date' => '2026-10-01', 'status' => 'finalizado']);

    preguntar($this, 'Quiero los mantenimientos vencidos del parque Villa Maria')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    $respuesta = preguntar($this, 'quiero ahora del parque de comet')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos_pendientes')
        ->assertJsonPath('datos.total', 1);

    expect($respuesta->json('mensaje'))->toContain('Comet');
});

test('un seguimiento de fecha continúa el tema aunque no haya resultados', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos de Plaza Mayor')->assertJsonPath('estado', 'completado');

    // "Mañana" ahora es un período válido y se aplica al parque del tema anterior.
    $respuesta = preguntar($this, 'y mañana')
        ->assertJsonPath('estado', 'sin_resultados')
        ->assertJsonPath('datos.total', 0);

    expect($respuesta->json('mensaje'))->toContain('Plaza Mayor');
});

test('un seguimiento puede pedir el reporte del tema actual', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos vencidos de la semana pasada')->assertJsonPath('estado', 'completado');

    preguntar($this, '¿y el reporte?')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('parametros_pendientes', ['formato']);

    $respuesta = preguntar($this, 'PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_pasada')
        ->assertJsonPath('datos.estado', 'vencido')
        ->assertJsonPath('datos.formato', 'pdf');

    expect($respuesta->json('archivo.nombre'))->toEndWith('.pdf');
});

test('una solicitud fuerte conserva lo entendido en la aclaración pendiente', function () {
    datosAsistente();

    preguntar($this, 'Genera el reporte de mantenimientos de la semana pasada')
        ->assertJsonPath('parametros_pendientes', ['formato']);

    preguntar($this, 'Genera el reporte en PDF')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.periodo', 'semana_pasada')
        ->assertJsonPath('datos.formato', 'pdf');
});

// ---------------------------------------------------------------- Lenguaje natural y fechas

test('entiende rangos de meses, días naturales y fechas informales', function () {
    $extractor = app(ExtractorParametros::class);

    // "Desde septiembre" va del 1/9 a hoy (14/10/2026).
    expect($extractor->extraer('reporte desde septiembre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte desde setiembre de 2025', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2025-09-01', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte de septiembre a noviembre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-11-30'])
        ->and($extractor->extraer('reporte desde setiembre a noviembre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-11-30'])
        ->and($extractor->extraer('reporte de septiembre de 2025 a marzo', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2025-09-01', 'hasta' => '2026-03-31'])
        ->and($extractor->extraer('reporte del 5 al 20 de octubre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-10-05', 'hasta' => '2026-10-20'])
        ->and($extractor->extraer('reporte entre el 5 y el 20 de octubre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-10-05', 'hasta' => '2026-10-20'])
        ->and($extractor->extraer('reporte del 28 de septiembre al 3 de octubre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-28', 'hasta' => '2026-10-03'])
        ->and($extractor->extraer('reporte del 28 al 3 de octubre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-28', 'hasta' => '2026-10-03'])
        ->and($extractor->extraer('reporte de septiembre al 20 de octubre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-10-20'])
        ->and($extractor->extraer('reporte del dia 14', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'fecha', 'desde' => '2026-10-14'])
        ->and($extractor->extraer('reporte del dia 14 del mes pasado', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'fecha', 'desde' => '2026-09-14'])
        ->and($extractor->extraer('reporte de anteayer', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'anteayer', 'desde' => '2026-10-12', 'hasta' => '2026-10-12'])
        ->and($extractor->extraer('reporte del ano pasado', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'anio_pasado', 'desde' => '2025-01-01', 'hasta' => '2025-12-31'])
        ->and($extractor->extraer('reporte de lo que va del ano', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'anio_actual', 'desde' => '2026-01-01', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte de septiembre a hoy', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte de septiembre a la fecha', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-10-14'])
        ->and($extractor->extraer('reporte hasta noviembre', 'generar_reporte'))
        ->toMatchArray(['periodo' => 'rango', 'desde' => '2026-01-01', 'hasta' => '2026-11-30']);
});

test('reconoce pedidos informales de reportes', function () {
    datosAsistente();

    preguntar($this, 'dame el reporte de mantenimientos de esta semana en pdf')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('datos.formato', 'pdf');

    preguntar($this, 'quiero que me des el reporte de mantenimientos de esta semana en excel')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('datos.formato', 'excel');

    preguntar($this, 'pásame los mantenimientos de esta semana en pdf')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'generar_reporte')
        ->assertJsonPath('datos.formato', 'pdf');
});

test('entiende estados informales y los conserva en el hilo', function () {
    datosAsistente();

    preguntar($this, '¿cuántos vencidos hay?')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'necesito los vencidos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos_pendientes')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'dame lo q está vencido este mes')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'pásame los mantenimientos que faltan')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos_pendientes')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'pásame los que ya están hechos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 3);
});

test('entiende un estado de mantenimiento sin repetir el módulo', function () {
    datosAsistente();

    preguntar($this, 'pásame los que ya están hechos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos')
        ->assertJsonPath('datos.total', 3);

    preguntar($this, 'dame los cancelados')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'dame lo que se hizo ayer')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('intencion', 'consultar_mantenimientos')
        ->assertJsonPath('datos.total', 1);
});

test('genera un reporte con un rango natural de meses', function () {
    datosAsistente();

    $respuesta = preguntar($this, 'dame el reporte de mantenimientos desde septiembre hasta noviembre en pdf')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.periodo', 'rango')
        ->assertJsonPath('datos.desde', '2026-09-01')
        ->assertJsonPath('datos.hasta', '2026-11-30')
        ->assertJsonPath('datos.registros', 6);

    expect($respuesta->json('archivo.nombre'))->toEndWith('.pdf');
});

test('un reporte de estados sin decir el módulo se entiende de mantenimientos', function () {
    datosAsistente();

    preguntar($this, 'dame el reporte de los vencidos de septiembre a noviembre en pdf')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.modulo', 'mantenimientos')
        ->assertJsonPath('datos.estado', 'vencido')
        ->assertJsonPath('datos.registros', 1);
});

test('consulta los vencidos de un rango natural', function () {
    datosAsistente();

    preguntar($this, 'necesito los vencidos de septiembre a noviembre')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});

test('un rango invertido pide corrección y luego se completa', function () {
    datosAsistente();

    preguntar($this, 'reporte de mantenimientos del 20/10/2026 al 05/10/2026 en pdf')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['fecha']);

    preguntar($this, 'del 5 al 20 de octubre')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.desde', '2026-10-05')
        ->assertJsonPath('datos.hasta', '2026-10-20')
        ->assertJsonPath('datos.formato', 'pdf');
});

test('un rango que empieza en el futuro pide confirmar la fecha', function () {
    datosAsistente();

    preguntar($this, 'reporte de mantenimientos desde diciembre en pdf')
        ->assertJsonPath('estado', 'requiere_aclaracion')
        ->assertJsonPath('parametros_pendientes', ['fecha']);
});

test('un seguimiento de confirmación mantiene el tema', function () {
    datosAsistente();

    preguntar($this, 'Mantenimientos vencidos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);

    preguntar($this, 'solo esos')
        ->assertJsonPath('estado', 'completado')
        ->assertJsonPath('datos.total', 1);
});
