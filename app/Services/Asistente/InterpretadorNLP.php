<?php

namespace App\Services\Asistente;

/**
 * Identifica la intención de un mensaje ya normalizado mediante reglas y
 * sinónimos. Es determinista: el mismo texto produce siempre el mismo
 * resultado y, si ninguna regla aplica, no se elige ninguna intención.
 */
class InterpretadorNLP
{
    /** Catálogo cerrado de intenciones (RF-03). */
    public const CATALOGO = [
        'INT-01' => 'consultar_dashboard',
        'INT-02' => 'listar_parques',
        'INT-03' => 'buscar_parque',
        'INT-04' => 'contar_parques',
        'INT-05' => 'consultar_mantenimientos',
        'INT-06' => 'consultar_mantenimientos_pendientes',
        'INT-07' => 'consultar_trabajadores',
        'INT-08' => 'contar_trabajadores',
        'INT-09' => 'consultar_actividades',
        'INT-10' => 'generar_reporte',
        'INT-11' => 'navegar_modulo',
        'INT-12' => 'solicitar_ayuda',
        'INT-13' => 'consultar_parques',
        'INT-14' => 'consultar_puntos_agua',
        'INT-15' => 'resumir_mantenimientos',
        'INT-16' => 'consultar_detalle_mantenimiento',
        'INT-17' => 'consultar_programacion',
        'INT-18' => 'consultar_justificaciones',
        'INT-19' => 'consultar_catalogos',
        'INT-20' => 'consultar_alertas',
    ];

    /**
     * @return array{intencion: ?string, operacion: string, fuerte: bool, bloqueo: ?string}
     */
    public function interpretar(string $texto): array
    {
        if ($texto === '') {
            return $this->resultado(null);
        }

        if (Diccionario::coincide($texto, Diccionario::AYUDA)) {
            return $this->resultado('solicitar_ayuda', 'ayuda', true);
        }

        $pideReporte = Diccionario::coincide($texto, Diccionario::REPORTE);
        $quitaFiltro = Diccionario::coincide($texto, Diccionario::QUITAR_FILTRO);

        if (! $quitaFiltro && (Diccionario::coincide($texto, Diccionario::ESCRITURA_DESTRUCTIVA)
            || (! $pideReporte && Diccionario::coincide($texto, Diccionario::ESCRITURA)))) {
            return $this->resultado(null, bloqueo: 'escritura');
        }

        if ($quitaFiltro) {
            return $this->resultado(null);
        }

        if (Diccionario::coincide($texto, Diccionario::NO_DISPONIBLE)) {
            return $this->resultado(null, bloqueo: 'no_disponible');
        }

        // "Quiero ir a reportes" navega; generar un reporte concreto no.
        if (Diccionario::coincide($texto, Diccionario::NAVEGAR) && ! $this->pideDetalle($texto)) {
            return $this->resultado('navegar_modulo', 'navegar', true);
        }

        if ($pideReporte || Diccionario::coincide($texto, Diccionario::GENERAR)) {
            return $this->resultado('generar_reporte', 'generar', true);
        }

        $modulos = Diccionario::modulosEn($texto);
        $modulo = $this->moduloDeConsulta($modulos);
        $detalle = $this->pideDetalle($texto);
        $justificaciones = Diccionario::coincide($texto, Diccionario::JUSTIFICACIONES);

        // "El documento de respaldo de la orden PM-…" se consulta como justificación.
        if ($justificaciones && preg_match('/\b(?:documento|respaldo|justificaci\w*|motivo)\b/u', $texto) === 1) {
            $detalle = false;
        }

        if (Diccionario::coincide($texto, Diccionario::NAVEGAR) && ! $detalle) {
            return $this->resultado('navegar_modulo', 'navegar', true);
        }

        if ($justificaciones && ! $detalle) {
            return $this->resultado('consultar_justificaciones', 'consultar', true);
        }

        if ($detalle) {
            return $this->resultado('consultar_detalle_mantenimiento', 'consultar', true);
        }

        if (Diccionario::coincide($texto, Diccionario::PROGRAMACION)) {
            return $this->resultado('consultar_programacion', 'consultar', true);
        }

        if (preg_match('/\balertas?\b|\bal dia\b|\bal día\b|\brequieren atencion\b/u', $texto) === 1) {
            return $this->resultado('consultar_alertas', Diccionario::coincide($texto, Diccionario::CONTAR) ? 'contar' : 'consultar', true);
        }

        // "Dame los mantenimientos en PDF": un formato y un módulo implican un reporte.
        if ($modulo !== null && Diccionario::coincide($texto, array_merge(...array_values(Diccionario::FORMATOS)))) {
            return $this->resultado('generar_reporte', 'generar', true);
        }

        $pideAgua = $this->pideAgua($texto, $modulo);

        if ($pideAgua) {
            return $this->resultado(
                'consultar_puntos_agua',
                Diccionario::coincide($texto, Diccionario::CONTAR) ? 'contar' : 'consultar',
                true,
            );
        }

        $agrupado = Diccionario::coincide($texto, Diccionario::AGRUPAR);

        if ($this->pideCatalogo($texto)) {
            return $this->resultado('consultar_catalogos', 'consultar', true);
        }

        if ($this->pideResumen($texto, $modulo)) {
            return $this->resultado('resumir_mantenimientos', 'resumir', true);
        }

        $pendientes = Diccionario::coincide($texto, Diccionario::PENDIENTES);
        $fuerte = Diccionario::coincide($texto, Diccionario::LISTAR);

        $mencionaPersonal = Diccionario::coincide($texto, [
            'trabajador', 'trabajadores', 'personal', 'jardinero', 'jardineros', 'operario', 'operarios',
            'obrero', 'obreros', 'empleado', 'empleados',
        ]);

        if ($pendientes && $modulo === 'trabajadores' && ! $mencionaPersonal) {
            return $this->resultado('consultar_mantenimientos_pendientes', 'consultar', true);
        }

        if (preg_match('/\basignad\w*/u', $texto) === 1 && $modulo !== 'trabajadores') {
            return $this->resultado('consultar_mantenimientos', 'consultar', true);
        }

        if (preg_match('/\b(?:cuantas horas|horas registradas|horas de trabajo|horas trabajadas|cuanto duro|cuanto demoro)\b/u', $texto) === 1 && $modulo !== 'mantenimientos') {
            return $this->resultado('consultar_trabajadores', 'consultar', true);
        }

        if (Diccionario::coincide($texto, Diccionario::CONTAR)) {
            if ($modulo === 'parques' && $this->pideSuperficie($texto)) {
                return $this->resultado('consultar_parques', 'contar', true);
            }

            return match ($modulo) {
                'parques' => $this->resultado('contar_parques', 'contar', true),
                'trabajadores' => $this->resultado('contar_trabajadores', 'contar', true),
                'mantenimientos' => $this->resultado(
                    $pendientes ? 'consultar_mantenimientos_pendientes' : 'consultar_mantenimientos',
                    'contar',
                    true,
                ),
                default => match (true) {
                    $pendientes => $this->resultado('consultar_mantenimientos_pendientes', 'contar', true),
                    $this->hayEstado($texto) => $this->resultado('consultar_mantenimientos', 'contar', true),
                    $this->pideDatosParque($texto) => $this->resultado('consultar_parques', 'contar', true),
                    default => $this->resultado(null),
                },
            };
        }

        if (Diccionario::coincide($texto, Diccionario::ACTIVIDADES)) {
            return $this->resultado('consultar_actividades', 'consultar', $fuerte);
        }

        if ($this->pideBusquedaTexto($texto)) {
            return $this->resultado('consultar_mantenimientos', 'consultar', true);
        }

        if ($modulo === null && $this->mencionaTipoMantenimiento($texto)) {
            return $this->resultado('consultar_mantenimientos', 'consultar', $fuerte);
        }

        if (Diccionario::coincide($texto, Diccionario::BUSCAR) && ! in_array($modulo, ['mantenimientos', 'trabajadores'], true)) {
            return $this->resultado('buscar_parque', 'buscar', true);
        }

        if ($modulo === 'parques') {
            return $this->resultado($agrupado || $this->tieneFiltroParque($texto) ? 'consultar_parques' : 'listar_parques', 'listar', $fuerte);
        }

        if ($modulo === null && $this->pideDatosParque($texto)) {
            return $this->resultado('consultar_parques', 'listar', $fuerte);
        }

        return match ($modulo) {
            'mantenimientos' => $this->resultado(
                $pendientes ? 'consultar_mantenimientos_pendientes' : 'consultar_mantenimientos',
                'consultar',
                $fuerte,
            ),
            'trabajadores' => $this->resultado('consultar_trabajadores', 'listar', $fuerte),
            'dashboard' => $this->resultado('consultar_dashboard', 'consultar', $fuerte),
            'configuracion' => $this->resultado('navegar_modulo', 'navegar', false),
            default => match (true) {
                $pendientes => $this->resultado('consultar_mantenimientos_pendientes', 'consultar', false),
                $this->hayEstado($texto) => $this->resultado('consultar_mantenimientos', 'consultar', $fuerte),
                $agrupado => $this->resultado('resumir_mantenimientos', 'resumir', false),
                default => $this->resultado(null),
            },
        };
    }

    /**
     * Precedencia cuando un mensaje menciona varios módulos: "mantenimientos del
     * parque X" es una consulta de mantenimientos filtrada por parque.
     *
     * @param  array<string, int>  $modulos
     */
    private function moduloDeConsulta(array $modulos): ?string
    {
        foreach (['mantenimientos', 'trabajadores', 'parques', 'dashboard', 'configuracion'] as $modulo) {
            if (isset($modulos[$modulo])) {
                return $modulo;
            }
        }

        return null;
    }

    private function hayEstado(string $texto): bool
    {
        return Diccionario::coincide($texto, array_merge(...array_values(Diccionario::ESTADOS)));
    }

    private function pideDetalle(string $texto): bool
    {
        return preg_match('/\bpm-\d+/u', $texto) === 1
            || Diccionario::coincide($texto, Diccionario::DETALLE);
    }

    /**
     * "Busca en las observaciones…" es una búsqueda de texto en mantenimientos,
     * no una búsqueda de parques.
     */
    private function pideBusquedaTexto(string $texto): bool
    {
        return preg_match('/\b(?:que digan|que mencionen|que contengan|con la palabra|busca en (?:las )?observaciones|en las observaciones)\b/u', $texto) === 1;
    }

    private function pideSuperficie(string $texto): bool
    {
        return preg_match('/\b(?:metros cuadrados|superficie|area verde|area total|area de uso publico|area publica|hectareas)\b/u', $texto) === 1;
    }

    private function mencionaTipoMantenimiento(string $texto): bool
    {
        return preg_match('/\b(?:riego|riegos|regar|poda|podas|podar|limpieza|limpiezas|barrido|corte de cesped|cesped|grama|fertilizacion|abono|fumigacion|siembra|trasplante|pintura|reparacion)\b/u', $texto) === 1;
    }

    /**
     * El agua solo cambia la intención cuando la pregunta habla de parques o del
     * punto de agua; "riego" sigue siendo un tipo de mantenimiento.
     */
    private function pideAgua(string $texto, ?string $modulo): bool
    {
        if (preg_match('/\briego(s)?\b|\bregar\b/u', $texto) === 1
            && preg_match('/\bpunto(s)? de agua\b|\babastecimiento\b|\bcobertura de agua\b/u', $texto) !== 1) {
            return false;
        }

        $explicito = Diccionario::coincide($texto, [
            'punto de agua', 'puntos de agua', 'tiene agua', 'tienen agua', 'sin agua', 'con agua',
            'abastecimiento', 'abastece de agua', 'se abastece', 'cobertura de agua', 'no tiene agua',
        ]);

        return Diccionario::coincide($texto, Diccionario::AGUA)
            && ($explicito || $modulo === 'parques' || $modulo === null || $this->mencionaParques($texto));
    }

    private function mencionaParques(string $texto): bool
    {
        return preg_match('/\bparque(s)?\b|\barea(s)? verde(s)?\b|\bplaza(s)?\b|\bovalo(s)?\b|\bjardin(es)?\b|\bberma(s)?\b|\balameda(s)?\b/u', $texto) === 1;
    }

    /**
     * Un catálogo se consulta cuando se pregunta qué tipos existen, no cuando se
     * pide el resumen de lo que se hizo.
     */
    private function pideCatalogo(string $texto): bool
    {
        $pideDatos = preg_match('/\b(?:se hicieron|se realizaron|se ejecutaron|faltan|falta|se hizo|realizados|pendientes|vencidos)\b/u', $texto) === 1
            || Diccionario::coincide($texto, Diccionario::AGRUPAR);

        if ($pideDatos) {
            return false;
        }

        if (Diccionario::coincide($texto, Diccionario::CATALOGOS)) {
            return true;
        }

        return preg_match('/\bque tipos (?:de \w+ )?(?:hay|existen|estan registrados|estan disponibles)\b/u', $texto) === 1;
    }

    /**
     * Un resumen agrupado necesita varios registros o una pregunta por tipo;
     * "los tipos de mantenimiento" por sí solo no es un resumen.
     */
    private function pideResumen(string $texto, ?string $modulo): bool
    {
        if ($modulo !== 'mantenimientos' && ! $this->hayEstado($texto) && ! Diccionario::coincide($texto, Diccionario::PENDIENTES)) {
            return false;
        }

        if (Diccionario::coincide($texto, ['que tipos de mantenimiento', 'tipos de mantenimiento se', 'tipos de trabajo se', 'tipos de mantenimiento faltan', 'tipos de mantenimiento hay', 'tipos de mantenimiento realizados'])
            || Diccionario::coincide($texto, ['por tipo', 'por cada tipo', 'de cada tipo', 'cuantos de cada', 'cuantas de cada', 'cantidad por', 'totales por', 'ranking'])) {
            return true;
        }

        return Diccionario::coincide($texto, ['agrupado', 'agrupados', 'agrupar', 'agrupame', 'resumen por', 'desglose', 'desglosado', 'comparar', 'comparacion', 'compara']);
    }

    private function tieneFiltroParque(string $texto): bool
    {
        return Diccionario::coincide($texto, Diccionario::AGUA)
            || Diccionario::coincide($texto, array_merge(...array_values(Diccionario::CONSERVACION)))
            || Diccionario::coincide($texto, array_merge(...array_values(Diccionario::EXISTENCIA)))
            || preg_match('/\b(?:activos?|inactivos?|zona|superficie|metros cuadrados|m2|area (?:verde|total|publica)|registr\w*|ubicacion|coordenadas|ficha|datos|mas (?:grande|grandes|amplio|amplios|antiguo|antiguos|reciente|recientes))\b/u', $texto) === 1;
    }

    /**
     * Preguntas sobre la ficha de un parque aunque no digan la palabra "parque".
     */
    private function pideDatosParque(string $texto): bool
    {
        return preg_match('/\bplaza(s)?\b|\bovalo(s)?\b|\bjardin(es)?\b|\bberma(s)?\b|\balameda(s)?\b/u', $texto) === 1
            || preg_match('/\b(?:superficie|metros cuadrados|m2|area verde|area total|area de uso publico|coordenadas|ubicacion|ficha)\b/u', $texto) === 1;
    }

    /**
     * @return array{intencion: ?string, operacion: string, fuerte: bool, bloqueo: ?string}
     */
    private function resultado(?string $intencion, string $operacion = '', bool $fuerte = false, ?string $bloqueo = null): array
    {
        return [
            'intencion' => $intencion,
            'operacion' => $operacion,
            'fuerte' => $fuerte,
            'bloqueo' => $bloqueo,
        ];
    }
}
