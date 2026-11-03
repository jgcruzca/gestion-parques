<?php

namespace App\Services\Asistente;

/**
 * Comprueba que la intención y sus parámetros están permitidos y completos.
 * Si falta información o es ambigua, pide aclaración en lugar de ejecutar.
 */
class ValidadorIntenciones
{
    /** Módulos de los que se puede generar un reporte con el módulo de reportes existente. */
    public const MODULOS_REPORTABLES = [
        'mantenimientos', 'parques', 'trabajadores', 'historial', 'justificaciones', 'alertas', 'agua',
    ];

    public const MODULOS_NAVEGABLES = ['dashboard', 'parques', 'mantenimientos', 'trabajadores', 'reportes', 'configuracion'];

    /**
     * @param  array<string, mixed>  $parametros
     * @return array{ok: bool, estado?: string, mensaje?: string, pendientes?: list<string>, candidatos?: array<string, list<array{id: int, nombre: string}>>}
     */
    public function validar(string $intencion, array $parametros): array
    {
        if (! in_array($intencion, InterpretadorNLP::CATALOGO, true)) {
            return $this->rechazo('no_reconocido', 'No pude identificar tu solicitud.');
        }

        if (! empty($parametros['fecha_invalida'])) {
            return $this->aclaracion(
                'La fecha indicada no es válida. Indícame una fecha real, por ejemplo 14/10/2026.',
                ['fecha'],
            );
        }

        if (! empty($parametros['rango_invalido'])) {
            return $this->aclaracion(
                'El rango de fechas no es válido o empieza después del final. Indícame un rango correcto, por ejemplo "del 5 al 20 de octubre".',
                ['fecha'],
            );
        }

        foreach (['parque' => 'parque', 'trabajador' => 'trabajador'] as $clave => $etiqueta) {
            if (! empty($parametros[$clave.'_candidatos'])) {
                $opciones = collect($parametros[$clave.'_candidatos'])
                    ->values()
                    ->map(fn (array $item, int $indice) => ($indice + 1).') '.$item['nombre'])
                    ->implode(' · ');

                return $this->aclaracion(
                    "Encontré varios resultados para el {$etiqueta}: {$opciones}. ¿Cuál necesitas? Responde con el número o el nombre.",
                    [$clave],
                    [$clave => $parametros[$clave.'_candidatos']],
                );
            }
        }

        if (! empty($parametros['tipo_mantenimiento_invalido'])) {
            $tipo = $parametros['tipo_mantenimiento_invalido'];

            return $this->rechazo(
                'sin_resultados',
                "No encontré un tipo de mantenimiento llamado «{$tipo}» en el catálogo del sistema.",
            );
        }

        return match ($intencion) {
            'generar_reporte' => $this->validarReporte($parametros),
            'navegar_modulo' => $this->validarNavegacion($parametros),
            'buscar_parque' => $this->validarBusquedaParque($parametros),
            'consultar_actividades' => $this->validarActividades($parametros),
            'consultar_detalle_mantenimiento' => $this->validarDetalle($parametros),
            default => ['ok' => true],
        };
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function validarDetalle(array $parametros): array
    {
        if (isset($parametros['numero_orden']) || isset($parametros['orden_id'])) {
            return ['ok' => true];
        }

        return $this->aclaracion('¿Qué orden quieres revisar? Indícame su número, por ejemplo PM-000014-2026.', ['numero_orden']);
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function validarReporte(array $parametros): array
    {
        $modulo = $parametros['modulo'] ?? null;

        if ($modulo === null) {
            return $this->aclaracion(
                '¿De qué módulo necesitas el reporte? Puedo generarlo de mantenimientos, parques, trabajadores, historial por parque, motivos de retraso, alertas o puntos de agua.',
                ['modulo'],
            );
        }

        if (! in_array($modulo, self::MODULOS_REPORTABLES, true)) {
            return $this->rechazo(
                'no_reconocido',
                "El módulo de {$modulo} no tiene reportes disponibles. Puedo generar reportes de mantenimientos, parques, trabajadores, historial por parque, motivos de retraso, alertas o puntos de agua.",
            );
        }

        if (($parametros['estado'] ?? null) === 'por_atender' && $modulo === 'mantenimientos') {
            return $this->aclaracion(
                '"Pendientes" incluye varios estados en el módulo de reportes. ¿Quieres los vencidos, los próximos, los que están en proceso o los finalizados?',
                ['estado'],
            );
        }

        $faltantes = [];
        $tieneFiltro = isset($parametros['tipo_mantenimiento']) || isset($parametros['estado'])
            || isset($parametros['origen']) || isset($parametros['asignacion'])
            || isset($parametros['numero_orden'])
            || (! empty($parametros['parque']) && empty($parametros['desde_hilo']))
            || (! empty($parametros['parques']) && empty($parametros['desde_hilo']));

        if ($modulo === 'mantenimientos' && ! isset($parametros['periodo']) && ! $tieneFiltro) {
            $faltantes[] = 'periodo';
        }

        if (! isset($parametros['formato'])) {
            $faltantes[] = 'formato';
        }

        if ($faltantes === []) {
            return ['ok' => true];
        }

        $texto = match ($faltantes) {
            ['periodo'] => '¿De qué período necesitas el reporte? Por ejemplo: hoy, ayer, esta semana, semana pasada, este mes, mes pasado o una fecha.',
            ['formato'] => '¿En qué formato lo necesitas: PDF o Excel?',
            default => '¿De qué período y en qué formato lo necesitas? Por ejemplo: "esta semana en PDF".',
        };

        return $this->aclaracion($texto, $faltantes);
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function validarNavegacion(array $parametros): array
    {
        if (! isset($parametros['modulo'])) {
            return $this->aclaracion(
                '¿A qué módulo quieres ir? Puedo llevarte a dashboard, parques, mantenimientos, trabajadores, reportes o configuración.',
                ['modulo'],
            );
        }

        return in_array($parametros['modulo'], self::MODULOS_NAVEGABLES, true)
            ? ['ok' => true]
            : $this->rechazo('no_reconocido', 'No pude identificar tu solicitud.');
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function validarBusquedaParque(array $parametros): array
    {
        if (isset($parametros['parque']) || ! empty($parametros['termino'])) {
            return ['ok' => true];
        }

        return $this->aclaracion('¿Qué parque quieres buscar? Indícame su nombre o código.', ['parque']);
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function validarActividades(array $parametros): array
    {
        if (isset($parametros['trabajador']) || isset($parametros['parque']) || ! empty($parametros['termino'])) {
            return ['ok' => true];
        }

        return $this->aclaracion('¿De qué trabajador o parque quieres ver las actividades?', ['trabajador']);
    }

    /**
     * @param  list<string>  $pendientes
     * @param  array<string, list<array{id: int, nombre: string}>>  $candidatos
     */
    private function aclaracion(string $mensaje, array $pendientes, array $candidatos = []): array
    {
        return [
            'ok' => false,
            'estado' => 'requiere_aclaracion',
            'mensaje' => $mensaje,
            'pendientes' => $pendientes,
            'candidatos' => $candidatos,
        ];
    }

    private function rechazo(string $estado, string $mensaje): array
    {
        return ['ok' => false, 'estado' => $estado, 'mensaje' => $mensaje, 'pendientes' => []];
    }
}
