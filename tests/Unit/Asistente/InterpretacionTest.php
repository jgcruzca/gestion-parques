<?php

use App\Services\Asistente\InterpretadorNLP;
use App\Services\Asistente\NormalizadorTexto;

function interpretarMensaje(string $mensaje): array
{
    $normalizador = new NormalizadorTexto;

    return (new InterpretadorNLP)->interpretar($normalizador->normalizar($mensaje));
}

test('el normalizador pasa a minúsculas, quita tildes y puntuación', function () {
    $normalizador = new NormalizadorTexto;

    expect($normalizador->normalizar('  ¿Cuántos   PARQUES tenemos?  '))->toBe('cuantos parques tenemos')
        ->and($normalizador->normalizar('Muéstrame el parque "Óvalo", por favor!'))->toBe('muestrame el parque ovalo por favor')
        ->and($normalizador->normalizar('PRQ-0005 y 14/10/2026'))->toBe('prq-0005 y 14/10/2026')
        ->and($normalizador->normalizar('<script>alert(1)</script>'))->toBe('script alert 1 script')
        ->and($normalizador->normalizar('   '))->toBe('');
});

test('entiende la abreviatura q y no toca códigos', function () {
    $normalizador = new NormalizadorTexto;

    expect($normalizador->normalizar('dame lo q esta vencido'))->toBe('dame lo que esta vencido')
        ->and($normalizador->normalizar('PRQ-0005'))->toBe('prq-0005')
        ->and($normalizador->normalizar('Q-123'))->toBe('q-123');
});

test('las formas informales de pedir conservan la intención', function () {
    expect(interpretarMensaje('dame lo q esta vencido este mes')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('pásame los mantenimientos que faltan')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('pásame los pendientes p')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('necesito los vencidos pe')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('quiero los vencidos')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('oe, pásame la chamba de esta semana')['intencion'])->toBe('consultar_mantenimientos')
        ->and(interpretarMensaje('necesito la chamba vencida')['intencion'])->toBe('consultar_mantenimientos_pendientes')
        ->and(interpretarMensaje('quiero que me des el reporte de mantenimientos')['intencion'])->toBe('generar_reporte')
        ->and(interpretarMensaje('pásame los mantenimientos de esta semana en pdf')['intencion'])->toBe('generar_reporte');
});

test('las cuatro redacciones equivalentes del reporte semanal comparten intención', function (string $mensaje) {
    $lectura = interpretarMensaje($mensaje);

    expect($lectura['intencion'])->toBe('generar_reporte')
        ->and($lectura['operacion'])->toBe('generar')
        ->and($lectura['bloqueo'])->toBeNull();
})->with([
    'Genera el reporte semanal de mantenimientos',
    'Quiero el informe de mantenimientos de esta semana',
    'Sácame los mantenimientos de esta semana',
    'Necesito el reporte semanal de mantenimiento',
]);

test('cada intención del catálogo se reconoce con una redacción habitual', function (string $mensaje, string $intencion) {
    expect(interpretarMensaje($mensaje)['intencion'])->toBe($intencion);
})->with([
    'INT-01 dashboard' => ['Dame el resumen del dashboard', 'consultar_dashboard'],
    'INT-02 listar parques' => ['Muéstrame los parques', 'listar_parques'],
    'INT-03 buscar parque' => ['Busca el parque Plaza Mayor', 'buscar_parque'],
    'INT-04 contar parques' => ['¿Cuántos parques tenemos registrados?', 'contar_parques'],
    'INT-05 mantenimientos' => ['¿Qué mantenimientos se realizaron ayer?', 'consultar_mantenimientos'],
    'INT-06 pendientes' => ['Muéstrame los mantenimientos pendientes', 'consultar_mantenimientos_pendientes'],
    'INT-07 trabajadores' => ['Lista de trabajadores', 'consultar_trabajadores'],
    'INT-08 contar trabajadores' => ['¿Cuántos trabajadores hay?', 'contar_trabajadores'],
    'INT-09 actividades' => ['Muéstrame las actividades de Rosa', 'consultar_actividades'],
    'INT-10 reporte' => ['Genera reporte de mantenimientos en PDF', 'generar_reporte'],
    'INT-11 navegar' => ['Llévame a gestión de parques', 'navegar_modulo'],
    'INT-12 ayuda' => ['Ayuda, ¿qué puedes hacer?', 'solicitar_ayuda'],
    'INT-13 parques filtrados' => ['Parques de la zona Norte activos', 'consultar_parques'],
    'INT-14 puntos de agua' => ['Dame los parques con punto de agua', 'consultar_puntos_agua'],
    'INT-15 resumen por tipo' => ['¿Qué tipos de mantenimiento se hicieron ayer?', 'resumir_mantenimientos'],
    'INT-16 detalle de orden' => ['Muéstrame el mantenimiento PM-000014-2026', 'consultar_detalle_mantenimiento'],
    'INT-17 programación' => ['¿Cada cuántos días se riega en Villa Maria?', 'consultar_programacion'],
    'INT-18 justificaciones' => ['Dame los motivos de retraso de este mes', 'consultar_justificaciones'],
    'INT-19 catálogos' => ['¿Qué tipos de mantenimiento están registrados?', 'consultar_catalogos'],
    'INT-20 alertas' => ['Dame las alertas de Comet', 'consultar_alertas'],
]);

test('las operaciones de escritura se bloquean y no producen intención', function (string $mensaje) {
    $lectura = interpretarMensaje($mensaje);

    expect($lectura['intencion'])->toBeNull()
        ->and($lectura['bloqueo'])->toBe('escritura');
})->with([
    'Elimina todos los parques',
    'Borra el mantenimiento 5',
    'Crea un nuevo parque',
    'Edita el trabajador Rosa',
    'Registra un mantenimiento para mañana',
    'Cancela el mantenimiento PM-000001',
]);

test('"registrados" no se confunde con la orden "registra"', function () {
    expect(interpretarMensaje('¿Cuántos parques están registrados?')['bloqueo'])->toBeNull();
});

test('las funciones fuera del alcance informan que no están disponibles', function (string $mensaje) {
    expect(interpretarMensaje($mensaje)['bloqueo'])->toBe('no_disponible');
})->with([
    'Haz una predicción de plagas',
    '¿Por qué se atrasaron los mantenimientos?',
    'Dame un análisis de tendencias',
]);

test('un mensaje sin relación con el sistema no elige ninguna intención', function (string $mensaje) {
    $lectura = interpretarMensaje($mensaje);

    expect($lectura['intencion'])->toBeNull()
        ->and($lectura['bloqueo'])->toBeNull();
})->with(['hola', 'cuéntame un chiste', '<script>alert(1)</script>', '???', '']);

test('un reporte pide aclaración aunque el mensaje sea corto', function () {
    expect(interpretarMensaje('Quiero un reporte')['intencion'])->toBe('generar_reporte');
});

test('la interpretación es determinista', function () {
    $primera = interpretarMensaje('Genera el reporte de mantenimientos de esta semana en PDF');

    foreach (range(1, 20) as $_) {
        expect(interpretarMensaje('Genera el reporte de mantenimientos de esta semana en PDF'))->toBe($primera);
    }
});
