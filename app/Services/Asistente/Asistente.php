<?php

namespace App\Services\Asistente;

/**
 * Orquesta el flujo: normalizar → interpretar → extraer → validar → ejecutar.
 * Mantiene la aclaración pendiente y el último tema para que una respuesta
 * corta complete la solicitud y un seguimiento conserve el hilo.
 */
class Asistente
{
    private const PARAMETROS_DE_RESPUESTA = [
        'modulo', 'periodo', 'desde', 'hasta', 'estado', 'formato', 'parque', 'parques', 'trabajador',
        'tipo_mantenimiento', 'origen', 'asignacion', 'duracion_min', 'duracion_max', 'retraso_min',
        'con_documento', 'con_foto', 'agua', 'agua_estado', 'agua_abastecimiento', 'parque_tipo',
        'parque_zona', 'parque_conservacion', 'parque_activo', 'superficie_min', 'superficie_max',
        'con_ubicacion', 'sin_ubicacion', 'con_observaciones', 'sin_observaciones', 'fecha_registro',
        'trabajador_activo', 'trabajador_cargo', 'trabajador_cuadrilla', 'sin_trabajos',
        'agrupar_por', 'limite', 'orden', 'numero_orden', 'orden_id', 'historial', 'busqueda_texto',
        'sin_foto', 'sin_documento', 'todo_el_historial',
    ];

    /** Intenciones cuyo resultado se recuerda como tema de la conversación. */
    private const INTENCIONES_CON_HILO = [
        'consultar_dashboard', 'listar_parques', 'buscar_parque', 'contar_parques', 'consultar_parques',
        'consultar_mantenimientos', 'consultar_mantenimientos_pendientes', 'resumir_mantenimientos',
        'consultar_trabajadores', 'contar_trabajadores', 'consultar_actividades', 'generar_reporte',
        'consultar_puntos_agua', 'consultar_detalle_mantenimiento', 'consultar_programacion',
        'consultar_justificaciones', 'consultar_catalogos', 'consultar_alertas',
    ];

    /** Parámetros con sentido para cada intención al heredar el tema anterior. */
    private const PARAMETROS_HEREDABLES = [
        'buscar_parque' => ['parque', 'termino'],
        'listar_parques' => ['parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'agua', 'agua_estado', 'agua_abastecimiento', 'superficie_min', 'superficie_max', 'con_foto', 'sin_foto', 'con_ubicacion', 'sin_ubicacion', 'con_observaciones', 'sin_observaciones', 'fecha_registro', 'periodo', 'desde', 'hasta', 'agrupar_por', 'limite', 'orden'],
        'consultar_parques' => ['parque', 'parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'agua', 'agua_estado', 'agua_abastecimiento', 'superficie_min', 'superficie_max', 'con_foto', 'sin_foto', 'con_ubicacion', 'sin_ubicacion', 'con_observaciones', 'sin_observaciones', 'fecha_registro', 'periodo', 'desde', 'hasta', 'agrupar_por', 'limite', 'orden'],
        'contar_parques' => ['parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'agua', 'agua_estado', 'agua_abastecimiento', 'superficie_min', 'superficie_max', 'con_foto', 'sin_foto', 'con_ubicacion', 'sin_ubicacion', 'fecha_registro', 'periodo', 'desde', 'hasta'],
        'consultar_puntos_agua' => ['parque', 'agua', 'agua_estado', 'agua_abastecimiento', 'parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'con_observaciones', 'sin_observaciones', 'agrupar_por', 'limite'],
        'consultar_mantenimientos' => ['periodo', 'desde', 'hasta', 'estado', 'parque', 'trabajador', 'tipo_mantenimiento', 'origen', 'asignacion', 'duracion_min', 'duracion_max', 'retraso_min', 'con_documento', 'con_foto', 'historial', 'busqueda_texto', 'agrupar_por', 'limite', 'orden', 'trabajador_cuadrilla', 'trabajador_cargo'],
        'consultar_mantenimientos_pendientes' => ['periodo', 'desde', 'hasta', 'estado', 'parque', 'trabajador', 'tipo_mantenimiento', 'origen', 'asignacion', 'duracion_min', 'duracion_max', 'retraso_min', 'con_documento', 'con_foto', 'agrupar_por', 'limite', 'orden', 'trabajador_cuadrilla', 'trabajador_cargo'],
        'resumir_mantenimientos' => ['periodo', 'desde', 'hasta', 'estado', 'parque', 'trabajador', 'tipo_mantenimiento', 'origen', 'asignacion', 'agrupar_por', 'limite', 'trabajador_cuadrilla', 'trabajador_cargo'],
        'consultar_trabajadores' => ['trabajador', 'trabajador_activo', 'trabajador_cargo', 'trabajador_cuadrilla', 'sin_trabajos', 'agrupar_por', 'limite'],
        'contar_trabajadores' => ['trabajador_activo', 'trabajador_cargo', 'trabajador_cuadrilla', 'sin_trabajos'],
        'consultar_actividades' => ['periodo', 'desde', 'hasta', 'parque', 'trabajador', 'estado', 'tipo_mantenimiento', 'agrupar_por', 'limite'],
        'consultar_justificaciones' => ['periodo', 'desde', 'hasta', 'parque', 'trabajador', 'tipo_mantenimiento', 'con_documento', 'busqueda_texto', 'fecha_registro', 'limite'],
        'consultar_programacion' => ['parque', 'tipo_mantenimiento'],
        'consultar_alertas' => ['periodo', 'desde', 'hasta', 'parque', 'parques', 'trabajador', 'tipo_mantenimiento', 'estado', 'agrupar_por', 'limite'],
        'generar_reporte' => ['modulo', 'periodo', 'desde', 'hasta', 'estado', 'formato', 'parque', 'trabajador', 'tipo_mantenimiento', 'origen', 'asignacion', 'duracion_min', 'duracion_max', 'parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'agua', 'agua_estado', 'agua_abastecimiento', 'trabajador_activo', 'trabajador_cargo', 'trabajador_cuadrilla'],
    ];

    public function __construct(
        private readonly NormalizadorTexto $normalizador,
        private readonly InterpretadorNLP $interprete,
        private readonly ExtractorParametros $extractor,
        private readonly ValidadorIntenciones $validador,
        private readonly EjecutorAcciones $ejecutor,
        private readonly GestorContexto $contexto,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function procesar(string $mensaje, bool $reiniciar = false): array
    {
        // Una conversación nueva (por ejemplo, tras la medianoche) descarta la aclaración y el tema anterior.
        if ($reiniciar) {
            $this->contexto->limpiar();
            $this->contexto->limpiarHilo();
        }

        $texto = $this->normalizador->normalizar($mensaje);
        $contexto = $this->contexto->obtener();
        $hilo = $this->contexto->hilo();

        // "De todos los parques" quita el filtro solo si hay un tema anterior.
        if ($hilo !== null && preg_match('/^(?:y\s+|ahora\s+)?(?:de\s+)?todos los parques$/u', $texto) === 1) {
            $texto = 'quitale el parque';
        }

        if ($contexto !== null && $this->esCancelacion($texto)) {
            $this->contexto->limpiar();
            $this->contexto->limpiarHilo();

            return $this->respuesta('completado', null, 'Listo, cancelé la solicitud pendiente.');
        }

        // "Olvídalo" sin una aclaración pendiente también descarta el tema anterior.
        if ($hilo !== null && in_array($texto, Diccionario::CANCELAR_CONTEXTO, true)) {
            $this->contexto->limpiarHilo();

            return $this->respuesta('completado', null, 'Listo, olvidé el tema anterior.');
        }

        $lectura = $this->interprete->interpretar($texto);

        if ($lectura['bloqueo'] === 'escritura') {
            return $this->respuesta('no_autorizado', null, 'El asistente es de solo lectura: no puede crear, editar ni eliminar registros. Usa los módulos del sistema para esa operación.');
        }

        if ($lectura['bloqueo'] === 'no_disponible') {
            return $this->respuesta('no_reconocido', null, 'Esa función no está disponible. Puedo consultar parques, trabajadores y mantenimientos, llevarte a un módulo o generar reportes.');
        }

        // Con una aclaración pendiente, una respuesta sin intención propia la completa.
        if ($contexto !== null && ! $lectura['fuerte']) {
            return $this->completar($contexto, $texto);
        }

        $intencion = $lectura['intencion'];
        $operacion = $lectura['operacion'];
        $referencial = false;
        $parametros = null;

        // "Quiero ahora del parque Comet": menciona un parque concreto, no pide listar parques.
        if ($hilo !== null
            && $this->esListaDeModulo($intencion)
            && ! $this->pideListaPlural($texto)
            && $this->hiloAceptaEntidad($hilo)) {
            $candidatos = $this->extractor->extraer($texto, $hilo['intencion']);

            if ($this->tieneEntidadReferida($candidatos, $intencion, $hilo['intencion'])) {
                $intencion = $hilo['intencion'];
                $operacion = $hilo['operacion'] ?? $operacion;
                $parametros = $candidatos;
                $referencial = true;
            }
        }

        // Una continuación puede cambiar el tema sin repetirlo ("¿y los vencidos?", "ahora en Excel").
        if ($intencion === null && $hilo !== null) {
            $derivada = $this->intencionDelHilo($texto, $hilo);

            if ($derivada !== null) {
                $intencion = $derivada['intencion'];
                $operacion = $derivada['operacion'];
            }
        }

        // Después de un reporte, "y el de trabajadores" cambia el módulo sin salir del reporte.
        if ($hilo !== null
            && $hilo['intencion'] === 'generar_reporte'
            && $intencion !== 'generar_reporte'
            && ! $lectura['fuerte']
            && Diccionario::modulosEn($texto) !== []
            && $this->esSeguimientoTexto($texto)) {
            $intencion = 'generar_reporte';
            $operacion = 'generar';
        }

        if ($intencion === null) {
            return $this->noReconocido();
        }

        $parametros ??= $this->extractor->extraer($texto, $intencion);

        // Una solicitud fuerte del mismo tipo conserva lo que la aclaración ya había entendido.
        if ($contexto !== null && $contexto['intencion'] === $intencion) {
            $parametros = [...$contexto['parametros'], ...$parametros];
        } elseif ($contexto !== null) {
            $this->contexto->limpiar();
        }

        if ($hilo !== null && ($referencial || $this->esSeguimiento($texto, $parametros, $lectura))) {
            $parametros = $this->heredar($hilo, $parametros, $intencion);
            $parametros['desde_hilo'] = true;

            if ($operacion === '') {
                $operacion = $hilo['operacion'] ?? 'consultar';
            }
        }

        $parametros['operacion'] = $operacion;
        $parametros['texto'] = $texto;

        return $this->resolver($intencion, $parametros);
    }

    /**
     * @param  array{intencion: string, parametros: array<string, mixed>, pendientes: list<string>, candidatos: array<string, list<array{id: int, nombre: string}>>, creado: int}  $contexto
     * @return array<string, mixed>
     */
    private function completar(array $contexto, string $texto): array
    {
        $intencion = $contexto['intencion'];
        $parametros = $contexto['parametros'];
        $nuevos = $this->extractor->extraer($texto, $intencion);
        $progreso = false;

        foreach (self::PARAMETROS_DE_RESPUESTA as $clave) {
            if (isset($nuevos[$clave])) {
                $parametros[$clave] = $nuevos[$clave];
                $progreso = true;
            }
        }

        foreach ($this->seleccion($texto, $contexto['candidatos']) as $clave => $entidad) {
            $parametros[$clave] = $entidad;
            unset($nuevos[$clave.'_candidatos']);
            $progreso = true;
        }

        foreach (['fecha_invalida', 'rango_invalido', 'parque_candidatos', 'trabajador_candidatos'] as $clave) {
            if (! empty($nuevos[$clave])) {
                $parametros[$clave] = $nuevos[$clave];
                $progreso = true;
            }
        }

        if (! $progreso) {
            return $this->noReconocido();
        }

        $parametros['texto'] = $texto;

        return $this->resolver($intencion, $parametros);
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    private function resolver(string $intencion, array $parametros): array
    {
        $validacion = $this->validador->validar($intencion, $parametros);

        if (! $validacion['ok']) {
            if ($validacion['estado'] === 'requiere_aclaracion') {
                $this->contexto->guardar($intencion, $parametros, $validacion['pendientes'], $validacion['candidatos']);

                $extra = ['parametros_pendientes' => $validacion['pendientes']];

                if ($validacion['candidatos'] !== []) {
                    $extra['opciones'] = collect($validacion['candidatos'])->flatten(1)->pluck('nombre')->values()->all();
                }

                return $this->respuesta('requiere_aclaracion', $intencion, $validacion['mensaje'], $extra);
            }

            $this->contexto->limpiar();

            return $this->respuesta($validacion['estado'], $intencion, $validacion['mensaje']);
        }

        $this->contexto->limpiar();
        $resultado = $this->ejecutor->ejecutar($intencion, $parametros);

        // Solo un tema resuelto se recuerda; una aclaración pendiente no reemplaza el hilo.
        if (in_array($intencion, self::INTENCIONES_CON_HILO, true)
            && in_array($resultado['estado'] ?? null, ['completado', 'sin_resultados'], true)) {
            $this->contexto->recordar($intencion, $parametros);
        }

        return $resultado;
    }

    /**
     * Resuelve respuestas como "2" o "la 2" entre las opciones ofrecidas.
     *
     * @param  array<string, list<array{id: int, nombre: string}>>  $candidatos
     * @return array<string, array{id: int, nombre: string}>
     */
    private function seleccion(string $texto, array $candidatos): array
    {
        if ($candidatos === [] || preg_match('/^(?:el |la |opcion |numero )?(\d{1,2})$/', $texto, $m) !== 1) {
            return [];
        }

        foreach ($candidatos as $clave => $opciones) {
            $elegida = $opciones[(int) $m[1] - 1] ?? null;

            if ($elegida !== null) {
                return [$clave => $elegida];
            }
        }

        return [];
    }

    /**
     * Intención de un seguimiento que no trae verbo propio.
     *
     * @param  array{intencion: string, operacion: string, parametros: array<string, mixed>, creado: int}  $hilo
     * @return array{intencion: string, operacion: string}|null
     */
    private function intencionDelHilo(string $texto, array $hilo): ?array
    {
        $parametros = $this->extractor->extraer($texto, null);

        // "en PDF", "mejor en Excel": continúa el tema como reporte.
        if (isset($parametros['formato'])) {
            return ['intencion' => 'generar_reporte', 'operacion' => 'generar'];
        }

        // "¿y cuántos?": conserva el tema y cambia a conteo.
        if (Diccionario::coincide($texto, Diccionario::CONTAR)) {
            if (in_array($hilo['intencion'], ['consultar_parques', 'consultar_puntos_agua'], true)) {
                return ['intencion' => $hilo['intencion'], 'operacion' => 'contar'];
            }

            return match ($this->moduloDelHilo($hilo)) {
                'parques' => ['intencion' => 'contar_parques', 'operacion' => 'contar'],
                'trabajadores' => ['intencion' => 'contar_trabajadores', 'operacion' => 'contar'],
                'mantenimientos' => ['intencion' => $hilo['intencion'], 'operacion' => 'contar'],
                default => null,
            };
        }

        if (! $this->esSeguimientoTexto($texto, $hilo['intencion'])) {
            return null;
        }

        return ['intencion' => $hilo['intencion'], 'operacion' => $hilo['operacion'] ?? 'consultar'];
    }

    private function esListaDeModulo(?string $intencion): bool
    {
        return in_array($intencion, [
            'listar_parques', 'consultar_parques', 'contar_parques', 'consultar_trabajadores', 'contar_trabajadores',
        ], true);
    }

    private function pideListaPlural(string $texto): bool
    {
        return Diccionario::coincide($texto, [
            'parques', 'areas verdes', 'trabajadores', 'operarios', 'empleados', 'obreros',
            'cuadrillas', 'jardineros', 'personal',
        ]);
    }

    /**
     * @param  array{intencion: string, operacion: string, parametros: array<string, mixed>, creado: int}  $hilo
     */
    private function hiloAceptaEntidad(array $hilo): bool
    {
        return match ($hilo['intencion']) {
            'consultar_mantenimientos',
            'consultar_mantenimientos_pendientes',
            'consultar_actividades',
            'resumir_mantenimientos',
            'consultar_parques',
            'consultar_puntos_agua',
            'consultar_justificaciones',
            'consultar_alertas',
            'buscar_parque' => true,
            'generar_reporte' => in_array($hilo['parametros']['modulo'] ?? null, ['mantenimientos', 'historial', 'justificaciones'], true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function tieneEntidadReferida(array $parametros, string $intencionActual, string $intencionHilo): bool
    {
        if (in_array($intencionActual, ['listar_parques', 'consultar_parques', 'contar_parques'], true)) {
            return isset($parametros['parque']) || ! empty($parametros['parques']) || ! empty($parametros['parque_candidatos']);
        }

        if (in_array($intencionActual, ['consultar_trabajadores', 'contar_trabajadores'], true)) {
            return $intencionHilo !== 'buscar_parque'
                && (isset($parametros['trabajador']) || ! empty($parametros['trabajador_candidatos']));
        }

        return false;
    }

    /**
     * Comprueba si el mensaje continúa el tema anterior en lugar de abrir uno nuevo.
     *
     * @param  array<string, mixed>  $parametros
     * @param  array{intencion: ?string, operacion: string, fuerte: bool, bloqueo: ?string}  $lectura
     */
    private function esSeguimiento(string $texto, array $parametros, array $lectura): bool
    {
        $modulos = Diccionario::modulosEn($texto);
        unset($modulos['reportes']);

        // Un verbo explícito con módulo inicia un tema nuevo ("muéstrame los trabajadores").
        if ($lectura['fuerte'] && $modulos !== []) {
            return false;
        }

        return $this->evaluarSeguimiento($texto, $parametros);
    }

    private function esSeguimientoTexto(string $texto, ?string $intencion = null): bool
    {
        return $this->evaluarSeguimiento($texto, $this->extractor->extraer($texto, $intencion));
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function evaluarSeguimiento(string $texto, array $parametros): bool
    {
        $modulos = Diccionario::modulosEn($texto);
        unset($modulos['reportes']);

        // "Solo esos", "así está bien": mantiene el tema sin cambiar filtros.
        if (Diccionario::coincide($texto, Diccionario::CONTINUAR)
            || Diccionario::coincide($texto, Diccionario::QUITAR_FILTRO)) {
            return true;
        }

        if (preg_match('/\b(?:sus|su|del mismo|de la misma|de ese|de esa|ahi|alli)\b/u', $texto) === 1) {
            return true;
        }

        // "¿y el reporte?" continúa el tema actual si no indica un módulo distinto.
        if (Diccionario::coincide($texto, Diccionario::REPORTE) && $modulos === []) {
            return true;
        }

        // Con un módulo propio, solo un conector explícito ("y el de trabajadores") mantiene el tema.
        if ($modulos !== []) {
            return $this->conector($texto);
        }

        if ($this->conector($texto)) {
            return $this->tieneFiltro($parametros) || Diccionario::coincide($texto, Diccionario::CONTAR);
        }

        // "Los atrasados" o "dame los cancelados" abren su propio tema: no heredan
        // el período o el parque de la consulta anterior.
        if ($this->pideLista($texto) || $this->hayEstado($texto)) {
            return false;
        }

        return $this->tieneFiltro($parametros);
    }

    private function pideLista(string $texto): bool
    {
        return preg_match('/\b(?:dame|damelos|muestrame|muestra|pasame|listame|lista|necesito|quiero|ver|cuales)\b/u', $texto) === 1;
    }

    private function hayEstado(string $texto): bool
    {
        return Diccionario::coincide($texto, array_merge(...array_values(Diccionario::ESTADOS)));
    }

    private function conector(string $texto): bool
    {
        return preg_match('/^(?:y|ahora|entonces|tambien|mejor|solo|pero|de esos|de esas|de ellos|de ellas|de ese|de esa|del mismo|de la misma)\b/u', $texto) === 1;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function tieneFiltro(array $parametros): bool
    {
        foreach ([
            'periodo', 'desde', 'hasta', 'estado', 'formato', 'parque', 'parques', 'trabajador', 'tipo_mantenimiento',
            'origen', 'asignacion', 'duracion_min', 'duracion_max', 'retraso_min', 'con_documento', 'con_foto',
            'agua', 'agua_estado', 'agua_abastecimiento', 'parque_tipo', 'parque_zona', 'parque_conservacion',
            'parque_activo', 'superficie_min', 'superficie_max', 'con_ubicacion', 'sin_ubicacion',
            'con_observaciones', 'sin_observaciones', 'trabajador_activo', 'trabajador_cargo',
            'trabajador_cuadrilla', 'sin_trabajos', 'agrupar_por', 'limite', 'orden', 'historial',
            'busqueda_texto', 'numero_orden', 'orden_id',
        ] as $clave) {
            if (isset($parametros[$clave])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{intencion: string, operacion: string, parametros: array<string, mixed>, creado: int}  $hilo
     * @param  array<string, mixed>  $nuevos
     * @return array<string, mixed>
     */
    private function heredar(array $hilo, array $nuevos, string $intencion): array
    {
        $anteriores = $hilo['parametros'];
        $nuevoParque = isset($nuevos['parque']) || ! empty($nuevos['parques']) || ! empty($nuevos['parque_candidatos']);
        $nuevoTrabajador = isset($nuevos['trabajador']) || ! empty($nuevos['trabajador_candidatos']);

        // Al cambiar de parque no se arrastra el trabajador anterior, y viceversa.
        if ($nuevoParque && ! $nuevoTrabajador) {
            unset($anteriores['trabajador']);
            unset($anteriores['parques']);
        }

        if ($nuevoTrabajador && ! $nuevoParque) {
            unset($anteriores['parque']);
        }

        $combinados = [...$anteriores, ...$nuevos];

        $quitar = $combinados['quitar'] ?? null;

        if ($quitar !== null) {
            $claves = match ($quitar) {
                'periodo' => ['periodo', 'desde', 'hasta'],
                'parque' => ['parque', 'termino', 'parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo', 'agua', 'agua_estado', 'agua_abastecimiento', 'superficie_min', 'superficie_max'],
                'estado' => ['estado'],
                'tipo_mantenimiento' => ['tipo_mantenimiento'],
                'trabajador' => ['trabajador'],
                default => ['periodo', 'desde', 'hasta', 'estado', 'parque', 'trabajador', 'tipo_mantenimiento', 'agua', 'agua_estado', 'agua_abastecimiento', 'parque_tipo', 'parque_zona', 'parque_conservacion', 'parque_activo'],
            };

            foreach ($claves as $clave) {
                unset($combinados[$clave]);
            }

            unset($combinados['quitar']);
        }

        // Un formato suelto conserva el módulo del tema anterior cuando es reportable.
        if ($intencion === 'generar_reporte' && ! isset($combinados['modulo'])) {
            $modulo = $this->moduloDelHilo($hilo);

            if ($modulo !== null && in_array($modulo, ValidadorIntenciones::MODULOS_REPORTABLES, true)) {
                $combinados['modulo'] = $modulo;
            }
        }

        $permitidos = self::PARAMETROS_HEREDABLES[$intencion] ?? [];

        if (! empty($nuevos['parques'])) {
            $permitidos[] = 'parques';
        }

        return array_intersect_key($combinados, array_flip($permitidos));
    }

    /**
     * @param  array{intencion: string, operacion: string, parametros: array<string, mixed>, creado: int}  $hilo
     */
    private function moduloDelHilo(array $hilo): ?string
    {
        $modulo = $hilo['parametros']['modulo'] ?? null;

        if (is_string($modulo) && $modulo !== '') {
            return $modulo;
        }

        return match ($hilo['intencion']) {
            'listar_parques', 'buscar_parque', 'contar_parques', 'consultar_parques', 'consultar_puntos_agua' => 'parques',
            'consultar_trabajadores', 'contar_trabajadores' => 'trabajadores',
            'consultar_mantenimientos', 'consultar_mantenimientos_pendientes', 'consultar_actividades',
            'resumir_mantenimientos', 'consultar_detalle_mantenimiento', 'consultar_justificaciones',
            'consultar_alertas' => 'mantenimientos',
            'consultar_dashboard' => 'dashboard',
            'consultar_programacion' => 'parques',
            'consultar_catalogos' => 'configuracion',
            default => null,
        };
    }

    private function esCancelacion(string $texto): bool
    {
        return count($this->normalizador->palabras($texto)) <= 4
            && Diccionario::coincide($texto, Diccionario::CANCELAR_CONTEXTO);
    }

    /**
     * @return array<string, mixed>
     */
    private function noReconocido(): array
    {
        return $this->respuesta('no_reconocido', null, 'No pude identificar tu solicitud. Puedes pedirme información sobre parques, trabajadores, mantenimientos o reportes.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function respuesta(string $estado, ?string $intencion, string $mensaje, array $extra = []): array
    {
        return ['estado' => $estado, 'intencion' => $intencion, 'mensaje' => $mensaje, ...$extra];
    }
}
