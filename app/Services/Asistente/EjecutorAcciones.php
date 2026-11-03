<?php

namespace App\Services\Asistente;

use App\Models\DashboardMonthlyTarget;
use App\Models\GreenAreaType;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\ParkSchedule;
use App\Models\Worker;
use App\Services\Maintenance\OperationalScope;
use App\Services\Reports\MaintenanceReportFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ejecuta las acciones autorizadas del asistente. Solo lee datos: no hay
 * ninguna operación de escritura, y las rutas de navegación y descarga salen
 * de una lista cerrada, nunca del texto del usuario.
 */
class EjecutorAcciones
{
    private const RUTAS = [
        'dashboard' => ['/dashboard', 'Dashboard'],
        'parques' => ['/parques', 'Gestión de parques'],
        'mantenimientos' => ['/mantenimientos', 'Mantenimientos'],
        'trabajadores' => ['/trabajadores', 'Trabajadores'],
        'reportes' => ['/reportes', 'Reportes'],
        'configuracion' => ['/configuracion', 'Configuración'],
        'perfil' => ['/settings/profile', 'Mi perfil'],
        'contrasena' => ['/settings/password', 'Cambiar contraseña'],
        'apariencia' => ['/settings/appearance', 'Apariencia'],
    ];

    private const TIPOS_REPORTE = [
        'mantenimientos' => 'maintenances',
        'parques' => 'parks',
        'trabajadores' => 'workers',
        'historial' => 'history',
        'justificaciones' => 'audit',
        'alertas' => 'alerts',
        'agua' => 'water',
    ];

    private const ETIQUETAS_ESTADO = [
        'vencido' => 'Vencido',
        'pendiente' => 'Para hoy',
        'proximo' => 'Próximo',
        'en_proceso' => 'En proceso',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
        'al_dia' => 'Al día',
    ];

    private const ETIQUETAS_AGRUPACION = [
        'tipo' => 'tipo de mantenimiento',
        'parque' => 'parque',
        'trabajador' => 'trabajador',
        'estado' => 'estado',
        'zona' => 'zona',
        'mes' => 'mes',
        'cuadrilla' => 'cuadrilla',
        'cargo' => 'cargo',
        'conservacion' => 'conservación',
    ];

    public function __construct(
        private readonly NormalizadorTexto $normalizador,
        private readonly VerificadorReporte $verificador,
    ) {}

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public function ejecutar(string $intencion, array $p): array
    {
        return match ($intencion) {
            'consultar_dashboard' => $this->dashboard(),
            'listar_parques', 'consultar_parques', 'contar_parques' => $this->parques($p, $intencion),
            'buscar_parque' => $this->buscarParque($p),
            'consultar_puntos_agua' => $this->puntosAgua($p),
            'consultar_mantenimientos', 'consultar_mantenimientos_pendientes' => $this->mantenimientos($intencion, $p),
            'resumir_mantenimientos' => $this->resumirMantenimientos($p),
            'consultar_detalle_mantenimiento' => $this->detalleMantenimiento($p),
            'consultar_trabajadores', 'contar_trabajadores' => $this->trabajadores($p, $intencion),
            'consultar_actividades' => $this->actividades($p),
            'consultar_programacion' => $this->programacion($p),
            'consultar_justificaciones' => $this->justificaciones($p),
            'consultar_alertas' => $this->alertas($p),
            'consultar_catalogos' => $this->catalogos($p),
            'generar_reporte' => $this->generarReporte($p),
            'navegar_modulo' => $this->navegar((string) ($p['modulo'] ?? '')),
            'solicitar_ayuda' => $this->ayuda(),
            default => $this->respuesta('no_reconocido', $intencion, 'No pude identificar tu solicitud.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function ayuda(): array
    {
        return $this->respuesta('completado', 'solicitar_ayuda', 'Puedo consultar información, llevarte a un módulo y generar reportes. No puedo crear, editar ni eliminar registros.', [
            'lista' => [
                '¿Cuántos parques tenemos registrados?',
                'Dame los parques con punto de agua',
                'Muéstrame los mantenimientos pendientes',
                '¿Qué mantenimientos se realizaron ayer?',
                '¿Qué tipos de mantenimiento se hicieron esta semana?',
                '¿Qué falta mañana?',
                '¿Cuántos trabajadores hay?',
                'Busca el parque Plaza Mayor',
                'Dame los motivos de retraso de este mes',
                'Llévame a gestión de parques',
                'Genera el reporte de mantenimientos de esta semana en PDF',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function navegar(string $modulo): array
    {
        if (! isset(self::RUTAS[$modulo])) {
            return $this->respuesta('requiere_aclaracion', 'navegar_modulo', '¿A qué módulo quieres ir? Puedo llevarte a dashboard, parques, mantenimientos, trabajadores, reportes, configuración, tu perfil o la apariencia.');
        }

        [$ruta, $etiqueta] = self::RUTAS[$modulo];

        return $this->respuesta('completado', 'navegar_modulo', "Te llevo a {$etiqueta}.", [
            'datos' => ['modulo' => $modulo],
            'navegacion' => ['ruta' => $ruta, 'etiqueta' => $etiqueta],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(): array
    {
        $hoy = $this->hoy();
        $inicioMes = $hoy->copy()->startOfMonth();
        $parques = Park::query()->get();
        $registros = MaintenanceRecord::query()->get();
        $porEstado = $registros->groupBy(fn (MaintenanceRecord $registro) => $registro->operationalStatus($hoy));

        $programadosMes = $registros->filter(fn (MaintenanceRecord $registro) => $registro->scheduled_date
            && $registro->scheduled_date->isSameMonth($hoy)
            && $registro->scheduled_date->isSameYear($hoy));

        $ejecutadosMes = $registros->filter(fn (MaintenanceRecord $registro) => $registro->execution_date
            && $registro->execution_date->isSameMonth($hoy)
            && $registro->execution_date->isSameYear($hoy)
            && in_array($registro->status, OperationalScope::TERMINAL_STATUSES, true));

        $aTiempo = $ejecutadosMes->filter(fn (MaintenanceRecord $registro) => ! $registro->scheduled_date
            || ! $registro->execution_date->isAfter($registro->scheduled_date));

        $superficie = (float) $parques->sum(fn (Park $parque) => (float) ($parque->green_area ?? $parque->area ?? 0));
        $conAgua = $parques->where('has_water_point', true)->count();
        $coberturaAgua = $parques->count() > 0 ? round(($conAgua / $parques->count()) * 100, 1) : 0;
        $parquesAtendidos = $ejecutadosMes->pluck('park_id')->filter()->unique()->count();
        $activos = $parques->where('is_active', true)->count();
        $meta = DashboardMonthlyTarget::query()
            ->whereDate('month', $inicioMes->toDateString())
            ->first();

        $tendencia = collect(range(5, 0))->map(function (int $meses) use ($registros, $hoy) {
            $mes = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($meses);
            $programados = $registros->filter(fn (MaintenanceRecord $registro) => $registro->scheduled_date
                && $registro->scheduled_date->isSameMonth($mes)
                && $registro->scheduled_date->isSameYear($mes))->count();
            $realizados = $registros->filter(fn (MaintenanceRecord $registro) => $registro->execution_date
                && $registro->execution_date->isSameMonth($mes)
                && $registro->execution_date->isSameYear($mes)
                && in_array($registro->status, OperationalScope::TERMINAL_STATUSES, true))->count();

            return [
                'mes' => ucfirst($mes->locale('es')->translatedFormat('M Y')),
                'programados' => $programados,
                'realizados' => $realizados,
            ];
        })->values()->all();

        $datos = [
            'parques_total' => $parques->count(),
            'parques_activos' => $activos,
            'parques_inactivos' => $parques->count() - $activos,
            'superficie_verde' => round($superficie, 1),
            'cobertura_agua' => $coberturaAgua,
            'parques_con_agua' => $conAgua,
            'mantenimientos_vencidos' => $porEstado->get('vencido', collect())->count(),
            'mantenimientos_hoy' => $porEstado->get('pendiente', collect())->count(),
            'mantenimientos_en_proceso' => $porEstado->get('en_proceso', collect())->count(),
            'mantenimientos_proximos' => $porEstado->get('proximo', collect())->count(),
            'mantenimientos_ejecutados_mes' => $ejecutadosMes->count(),
            'mantenimientos_programados_mes' => $programadosMes->count(),
            'cumplimiento_mes' => $programadosMes->count() > 0
                ? min(100, (int) round(($ejecutadosMes->count() / $programadosMes->count()) * 100))
                : 0,
            'puntualidad_mes' => $ejecutadosMes->count() > 0
                ? (int) round(($aTiempo->count() / $ejecutadosMes->count()) * 100)
                : 0,
            'parques_atendidos_mes' => $parquesAtendidos,
            'cobertura_parques_mes' => $activos > 0 ? min(100, (int) round(($parquesAtendidos / $activos) * 100)) : 0,
            'meta_mantenimientos' => $meta?->maintenance_goal ?? max($programadosMes->count(), 1),
            'meta_vencidos' => $meta?->overdue_limit ?? 0,
            'meta_cobertura_parques' => $meta?->park_coverage_goal ?? 80,
            'tendencia' => $tendencia,
        ];

        return $this->respuesta('completado', 'consultar_dashboard', sprintf(
            'Resumen operativo: %d parques (%d activos, %.1f m² de área verde y %.1f %% con punto de agua). '
            .'Mantenimientos: %d vencidos, %d para hoy, %d en proceso y %d próximos. '
            .'Este mes: %d programados, %d realizados, %d %% de cumplimiento y %d %% de puntualidad.',
            $datos['parques_total'],
            $datos['parques_activos'],
            $datos['superficie_verde'],
            $datos['cobertura_agua'],
            $datos['mantenimientos_vencidos'],
            $datos['mantenimientos_hoy'],
            $datos['mantenimientos_en_proceso'],
            $datos['mantenimientos_proximos'],
            $datos['mantenimientos_programados_mes'],
            $datos['mantenimientos_ejecutados_mes'],
            $datos['cumplimiento_mes'],
            $datos['puntualidad_mes'],
        ), [
            'datos' => $datos,
            'navegacion' => ['ruta' => '/dashboard', 'etiqueta' => 'Abrir dashboard'],
        ]);
    }

    /**
     * Listado, conteo y filtros de parques.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function parques(array $p, string $intencion): array
    {
        $consulta = $this->consultaParques($p);
        $total = (clone $consulta)->count();

        if (isset($p['parque']) && $total === 1) {
            $parque = $consulta->first();

            return $this->detalleParque($parque, $intencion);
        }

        if ($total === 0) {
            return $this->respuesta('sin_resultados', $intencion, 'No encontré parques con los filtros indicados.', [
                'datos' => ['total' => 0],
            ]);
        }

        $descripcion = $this->describirParques($p);
        $agrupar = $p['agrupar_por'] ?? null;

        if ($agrupar !== null) {
            $agrupados = $this->agruparParques($consulta->get(), (string) $agrupar);

            return $this->respuesta('completado', $intencion, sprintf(
                'Hay %d parques %s, agrupados por %s.',
                $total,
                $descripcion,
                self::ETIQUETAS_AGRUPACION[$agrupar] ?? $agrupar,
            ), [
                'datos' => ['total' => $total, 'grupos' => $agrupados],
                'lista' => collect($agrupados)->map(fn (int $cantidad, string $nombre) => $nombre.' · '.$this->plural($cantidad, 'parque', 'parques'))->values()->all(),
                'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
            ]);
        }

        if (($p['operacion'] ?? null) === 'contar' || $intencion === 'contar_parques') {
            $datos = $this->conteoParques($consulta->get());

            if (preg_match('/\b(?:metros cuadrados|superficie|area verde total|hectareas)\b/u', (string) ($p['texto'] ?? '')) === 1) {
                $superficie = (float) $consulta->get()->sum(fn (Park $parque) => (float) ($parque->green_area ?? $parque->area ?? 0));

                return $this->respuesta('completado', $intencion, sprintf(
                    'El área verde registrada en %d parques %s es de %s m².',
                    $total,
                    $descripcion,
                    number_format($superficie, 1, ',', '.'),
                ), ['datos' => ['total' => $total, 'superficie_verde' => round($superficie, 1)]]);
            }

            return $this->respuesta('completado', $intencion, sprintf(
                'Hay %d parques %s (%d activos y %d inactivos).',
                $total,
                $descripcion,
                $datos['activos'],
                $datos['inactivos'],
            ), ['datos' => ['total' => $total, ...$datos]]);
        }

        $limite = $this->limite($p);

        return $this->respuesta('completado', $intencion, sprintf(
            'Encontré %d parques %s.%s',
            $total,
            $descripcion,
            $total > $limite ? ' Muestro los primeros '.$limite.'.' : '',
        ), [
            'datos' => $this->conteoParques($consulta->get()),
            'lista' => $consulta->limit($limite)->get()->map(fn (Park $parque) => $this->lineaParque($parque, isset($p['agua'])))->values()->all(),
            'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function buscarParque(array $p): array
    {
        $parques = Park::query()->orderBy('name')->get();

        if (isset($p['parque'])) {
            $parques = $parques->where('id', $p['parque']['id']);
            $busqueda = $p['parque']['nombre'];
        } else {
            $busqueda = (string) ($p['termino'] ?? '');
            $palabras = $this->normalizador->palabras($this->normalizador->normalizar($busqueda));

            $parques = $parques->filter(function (Park $parque) use ($palabras) {
                $texto = $this->normalizador->normalizar(implode(' ', array_filter([
                    $parque->name, $parque->code, $parque->address, $parque->zone, $parque->reference,
                ])));

                foreach ($palabras as $palabra) {
                    if (! str_contains($texto, $palabra)) {
                        return false;
                    }
                }

                return true;
            });
        }

        if ($parques->isEmpty()) {
            return $this->respuesta('sin_resultados', 'buscar_parque', "No encontré parques que coincidan con «{$busqueda}».");
        }

        return $this->respuesta('completado', 'buscar_parque', $this->encabezado($parques->count(), 'parque encontrado', 'parques encontrados'), [
            'lista' => $parques->take($this->limite($p))->map(fn (Park $parque) => $this->lineaParque($parque, true))->values()->all(),
            'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function detalleParque(Park $parque, string $intencion): array
    {
        $lineas = [
            'Código: '.($parque->code ?? 'sin código'),
            'Tipo: '.($parque->green_area_type ?? 'sin tipo'),
            'Ubicación: '.(collect([$parque->address, $parque->zone])->filter()->implode(' · ') ?: 'sin ubicación registrada'),
            'Referencia: '.($parque->reference ?: 'sin referencia'),
            'Conservación: '.ucfirst((string) ($parque->conservation_status ?? 'sin estado')),
            'Estado: '.($parque->is_active ? 'Activo' : 'Inactivo'),
            'Área de uso público: '.$this->superficie($parque->public_use_area),
            'Área verde: '.$this->superficie($parque->green_area ?? $parque->area),
            'Área total: '.$this->superficie($parque->total_area),
            'Punto de agua: '.($parque->has_water_point
                ? 'Sí · '.($parque->water_point_status ?: 'sin estado')
                : 'No registra'),
        ];

        if ($parque->has_water_point) {
            $lineas[] = 'Abastecimiento: '.($parque->water_supply_type ?: 'sin dato');
            $lineas[] = 'Observaciones del agua: '.($parque->water_point_observations ?: 'sin observaciones');
        }

        if ($parque->latitude !== null && $parque->longitude !== null) {
            $lineas[] = 'Coordenadas: '.$parque->latitude.', '.$parque->longitude;
        }

        if ($parque->general_observations) {
            $lineas[] = 'Observaciones: '.$parque->general_observations;
        }

        $lineas[] = 'Registrado: '.($parque->registration_date?->format('d/m/Y') ?? 'sin fecha');

        return $this->respuesta('completado', $intencion, 'Datos del parque '.$parque->name.'.', [
            'datos' => ['id' => $parque->id, 'nombre' => $parque->name, 'codigo' => $parque->code],
            'lista' => $lineas,
            'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
        ]);
    }

    /**
     * Consultas sobre puntos de agua.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function puntosAgua(array $p): array
    {
        // El universo son todos los parques que cumplen los demás filtros: la
        // pregunta por el agua no debe reducir el total de referencia.
        $base = $p;
        unset($base['agua'], $base['agua_estado'], $base['agua_abastecimiento']);

        $parques = $this->consultaParques($base)->orderBy('name')->get();
        $total = $parques->count();

        if ($total === 0) {
            return $this->respuesta('sin_resultados', 'consultar_puntos_agua', 'No encontré parques con los filtros indicados.', [
                'datos' => ['total' => 0],
            ]);
        }

        $conAgua = $parques->where('has_water_point', true);
        $operativos = $conAgua->filter(fn (Park $parque) => in_array(mb_strtolower((string) $parque->water_point_status), ['operativo', 'bueno', 'funcional'], true));
        $noOperativos = $conAgua->filter(fn (Park $parque) => in_array(mb_strtolower((string) $parque->water_point_status), ['no operativo', 'inoperativo', 'malo'], true));
        $conObservaciones = $conAgua->filter(fn (Park $parque) => filled($parque->water_point_observations));
        $sinAgua = $parques->where('has_water_point', false);

        $datos = [
            'total' => $total,
            'con_agua' => $conAgua->count(),
            'sin_agua' => $sinAgua->count(),
            'operativos' => $operativos->count(),
            'no_operativos' => $noOperativos->count(),
            'con_observaciones' => $conObservaciones->count(),
            'cobertura' => round(($conAgua->count() / $total) * 100, 1),
        ];

        if (isset($p['parque']) && $total === 1) {
            $parque = $parques->first();
            $lineas = [
                'Punto de agua: '.($parque->has_water_point ? 'Sí' : 'No'),
                'Estado: '.($parque->water_point_status ?: 'sin estado'),
                'Abastecimiento: '.($parque->water_supply_type ?: 'sin dato'),
                'Observaciones: '.($parque->water_point_observations ?: 'sin observaciones'),
            ];

            return $this->respuesta('completado', 'consultar_puntos_agua', 'Punto de agua de '.$parque->name.'.', [
                'datos' => ['id' => $parque->id, 'con_agua' => (bool) $parque->has_water_point],
                'lista' => $lineas,
                'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
            ]);
        }

        if (($p['agrupar_por'] ?? null) !== null) {
            $agrupados = $this->agruparParques($parques, (string) $p['agrupar_por']);

            return $this->respuesta('completado', 'consultar_puntos_agua', sprintf(
                'Cobertura de agua: %d de %d parques (%s %%). Agrupados por %s.',
                $datos['con_agua'],
                $total,
                number_format($datos['cobertura'], 1, ',', '.'),
                self::ETIQUETAS_AGRUPACION[$p['agrupar_por']] ?? $p['agrupar_por'],
            ), [
                'datos' => [...$datos, 'grupos' => $agrupados],
                'lista' => collect($agrupados)->map(fn (int $cantidad, string $nombre) => $nombre.' · '.$this->plural($cantidad, 'parque', 'parques'))->values()->all(),
            ]);
        }

        if (($p['operacion'] ?? null) === 'contar') {
            return $this->respuesta('completado', 'consultar_puntos_agua', sprintf(
                'De %d parques, %d tienen punto de agua (%s %%) y %d no lo tienen. Operativos: %d; no operativos: %d; con observaciones: %d.',
                $total,
                $datos['con_agua'],
                number_format($datos['cobertura'], 1, ',', '.'),
                $datos['sin_agua'],
                $datos['operativos'],
                $datos['no_operativos'],
                $datos['con_observaciones'],
            ), ['datos' => $datos]);
        }

        $visibles = match ($p['agua'] ?? null) {
            'con' => $conAgua,
            'sin' => $sinAgua,
            default => $parques,
        };

        if (($p['agua_estado'] ?? null) !== null) {
            $buscado = mb_strtolower($p['agua_estado']);
            $visibles = $conAgua->filter(fn (Park $parque) => mb_strtolower((string) $parque->water_point_status) === $buscado);
        }

        if (($p['agua_abastecimiento'] ?? null) !== null) {
            $visibles = $visibles->filter(fn (Park $parque) => $parque->water_supply_type === $p['agua_abastecimiento']);
        }

        $limite = $this->limite($p);

        $mensaje = ($p['agua'] ?? null) === 'sin'
            ? sprintf('De %d parques, %d no tienen punto de agua.', $total, $datos['sin_agua'])
            : sprintf(
                'De %d parques, %d tienen punto de agua (%s %%): %d operativos y %d no operativos.',
                $total,
                $datos['con_agua'],
                number_format($datos['cobertura'], 1, ',', '.'),
                $datos['operativos'],
                $datos['no_operativos'],
            );

        return $this->respuesta('completado', 'consultar_puntos_agua', $mensaje, [
            'datos' => $datos,
            'lista' => $visibles->take($limite)->map(fn (Park $parque) => $this->lineaAgua($parque))->values()->all(),
            'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function actividades(array $p): array
    {
        if (! isset($p['trabajador']) && ! isset($p['parque'])) {
            $nombre = (string) ($p['termino'] ?? '');

            return $this->respuesta('sin_resultados', 'consultar_actividades', "No encontré un trabajador ni un parque llamado «{$nombre}».");
        }

        return $this->mantenimientos('consultar_actividades', $p);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function mantenimientos(string $intencion, array $p): array
    {
        $estado = $p['estado'] ?? ($intencion === 'consultar_mantenimientos_pendientes' ? 'por_atender' : null);
        $historial = $intencion === 'consultar_actividades' || isset($p['parque']) || ! empty($p['parques']) || isset($p['trabajador'])
            || ! empty($p['historial']) || $this->filtrosDeHistorial($p);
        $consulta = $this->consultaMantenimientos($p, $estado, $historial);
        $descripcion = $this->describir($p, $estado);
        $total = (clone $consulta)->count();
        $contar = ($p['operacion'] ?? null) === 'contar';

        if ($total === 0) {
            return $this->respuesta('sin_resultados', $intencion, "No encontré mantenimientos {$descripcion}.", ['datos' => ['total' => 0]]);
        }

        $datos = ['total' => $total];

        if ($estado === 'por_atender' && ! isset($p['desde'])) {
            $inicioHoy = $this->hoy()->toDateTimeString();
            $inicioManana = $this->hoy()->addDay()->toDateTimeString();
            $datos['vencidos'] = OperationalScope::overdue($this->consultaBase($p), $inicioHoy)->count();
            $datos['para_hoy'] = OperationalScope::dueToday($this->consultaBase($p), $inicioHoy, $inicioManana)->count();
        }

        if ($contar) {
            return $this->respuesta('completado', $intencion, sprintf('Hay %d mantenimientos %s.', $total, $descripcion), [
                'datos' => $datos,
                'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
            ]);
        }

        if (($p['agrupar_por'] ?? null) !== null) {
            return $this->resumirMantenimientos([...$p, 'intencion' => $intencion]);
        }

        $hoy = $this->hoy();
        $limite = $this->limite($p);
        $mostrados = $consulta->limit($limite)->get();
        $mensaje = sprintf('Encontré %d mantenimientos %s', $total, $descripcion);

        if ($estado === 'por_atender' && isset($datos['vencidos'])) {
            $mensaje .= sprintf(' (%d vencidos y %d para hoy)', $datos['vencidos'], $datos['para_hoy']);
        }

        $mensaje .= '.';

        if ($total > $limite) {
            $mensaje .= ' Muestro los primeros '.$limite.'.';
        }

        return $this->respuesta('completado', $intencion, $mensaje, [
            'datos' => $datos,
            'lista' => $mostrados->map(fn (MaintenanceRecord $registro) => $this->lineaMantenimiento($registro, $hoy, $p))->all(),
            'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
        ]);
    }

    /**
     * Resumen agrupado por tipo, parque, trabajador, estado, zona, mes, cuadrilla o cargo.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function resumirMantenimientos(array $p): array
    {
        if (! empty($p['comparar'])) {
            return $this->compararMantenimientos($p);
        }

        $estado = $p['estado'] ?? null;
        $consulta = $this->consultaMantenimientos($p, $estado, true);
        $registros = $consulta->get();
        $agrupar = (string) ($p['agrupar_por'] ?? 'tipo');
        $descripcion = $this->describir($p, $estado);

        if ($registros->isEmpty()) {
            return $this->respuesta('sin_resultados', 'resumir_mantenimientos', "No encontré mantenimientos {$descripcion}.", [
                'datos' => ['total' => 0],
            ]);
        }

        $grupos = [];
        $horas = [];
        $parques = [];

        foreach ($registros as $registro) {
            $nombre = $this->claveAgrupacion($registro, $agrupar) ?: 'Sin dato';
            $grupos[$nombre] = ($grupos[$nombre] ?? 0) + 1;
            $horas[$nombre] = ($horas[$nombre] ?? 0) + (int) $registro->duration_minutes;
            $parques[$nombre][$registro->park_id] = true;
        }

        arsort($grupos);

        $lineas = collect($grupos)->map(function (int $cantidad, string $nombre) use ($horas, $parques) {
            $detalle = [$nombre, $this->plural($cantidad, 'mantenimiento', 'mantenimientos')];

            if (($horas[$nombre] ?? 0) > 0) {
                $detalle[] = round($horas[$nombre] / 60, 1).' h';
            }

            if (count($parques[$nombre] ?? []) > 0) {
                $detalle[] = $this->plural(count($parques[$nombre]), 'parque', 'parques');
            }

            return implode(' · ', $detalle);
        })->values()->all();

        return $this->respuesta('completado', 'resumir_mantenimientos', sprintf(
            'Encontré %d mantenimientos %s, agrupados por %s: %d grupos.',
            $registros->count(),
            $descripcion,
            self::ETIQUETAS_AGRUPACION[$agrupar] ?? $agrupar,
            count($grupos),
        ), [
            'datos' => [
                'total' => $registros->count(),
                'agrupado_por' => $agrupar,
                'grupos' => $grupos,
                'horas' => array_map(fn (int $minutos) => round($minutos / 60, 1), $horas),
            ],
            'lista' => $lineas,
            'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
        ]);
    }

    /**
     * Compara el mismo tipo de consulta en dos meses.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function compararMantenimientos(array $p): array
    {
        $estado = $p['estado'] ?? null;
        $resultados = [];
        $lineas = [];

        foreach ($p['comparar'] as $periodo) {
            $consulta = $this->consultaMantenimientos([
                ...$p,
                'periodo' => 'rango',
                'desde' => $periodo['desde'],
                'hasta' => $periodo['hasta'],
            ], $estado, true);

            $total = (clone $consulta)->count();
            $resultados[$periodo['etiqueta']] = $total;
            $lineas[] = $periodo['etiqueta'].' · '.$this->plural($total, 'mantenimiento', 'mantenimientos');
        }

        $valores = array_values($resultados);
        $diferencia = ($valores[1] ?? 0) - ($valores[0] ?? 0);
        $base = $valores[0] ?? 0;
        $porcentaje = $base > 0 ? round(($diferencia / $base) * 100, 1) : null;

        return $this->respuesta('completado', 'resumir_mantenimientos', sprintf(
            'Comparación de mantenimientos %s: %s. Diferencia: %s%s.',
            $this->describir($p, $estado),
            collect($resultados)->map(fn (int $total, string $etiqueta) => $etiqueta.': '.$total)->implode(' · '),
            $diferencia >= 0 ? '+' : '',
            $diferencia.($porcentaje !== null ? ' ('.($diferencia >= 0 ? '+' : '').$porcentaje.' %)' : ''),
        ), [
            'datos' => [
                'comparacion' => $resultados,
                'diferencia' => $diferencia,
                'variacion_porcentual' => $porcentaje,
            ],
            'lista' => $lineas,
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function detalleMantenimiento(array $p): array
    {
        $consulta = MaintenanceRecord::query()->with(['park', 'worker', 'schedule']);

        if (isset($p['numero_orden'])) {
            $consulta->where('maintenance_number', $p['numero_orden']);
        } elseif (isset($p['orden_id'])) {
            $consulta->whereKey($p['orden_id']);
        } else {
            return $this->respuesta('requiere_aclaracion', 'consultar_detalle_mantenimiento', '¿Qué orden quieres revisar? Indícame su número, por ejemplo PM-000014-2026.', [
                'parametros_pendientes' => ['numero_orden'],
            ]);
        }

        $registro = $consulta->first();

        if ($registro === null) {
            return $this->respuesta('sin_resultados', 'consultar_detalle_mantenimiento', 'No encontré esa orden de mantenimiento.');
        }

        $hoy = $this->hoy();
        $estado = $registro->operationalStatus($hoy);
        $lineas = [
            'Número: '.($registro->maintenance_number ?? 'sin número'),
            'Parque: '.($registro->park?->name ?? 'sin parque'),
            'Tipo: '.($registro->maintenance_type ?: 'sin tipo'),
            'Origen: '.($registro->is_extraordinary ? 'Adicional' : 'Programación periódica'),
            'Estado: '.(self::ETIQUETAS_ESTADO[$estado] ?? ucfirst($estado)),
            'Programado: '.($registro->scheduled_date?->format('d/m/Y') ?? 'sin fecha'),
            'Fecha de alerta: '.($registro->alert_date?->format('d/m/Y') ?? 'sin alerta'),
            'Días de anticipación: '.($registro->advance_days ?? 'sin dato'),
            'Ejecutado: '.($registro->execution_date?->format('d/m/Y') ?? 'sin fecha'),
            'Trabajador: '.($registro->worker?->full_name ?? $registro->responsible_worker ?? 'Por asignar'),
            'Horario: '.collect([$registro->start_time ? substr($registro->start_time, 0, 5) : null, $registro->end_time ? substr($registro->end_time, 0, 5) : null])->filter()->implode(' - '),
            'Duración: '.($registro->duration_minutes ? round($registro->duration_minutes / 60, 1).' h' : 'sin registro'),
        ];

        foreach ([
            'Observación inicial' => $registro->initial_observation,
            'Trabajo realizado' => $registro->work_description,
            'Resultado' => $registro->result,
            'Motivo adicional' => $registro->reason,
            'Observaciones' => $registro->observations,
            'Documento' => $registro->document_name,
        ] as $etiqueta => $valor) {
            if (filled($valor)) {
                $lineas[] = $etiqueta.': '.$valor;
            }
        }

        $lineas[] = 'Fotografía inicial: '.($registro->photo_before_url ? 'Sí' : 'No');
        $lineas[] = 'Fotografía final: '.($registro->photo_after_url ? 'Sí' : 'No');

        return $this->respuesta('completado', 'consultar_detalle_mantenimiento', 'Detalle de la orden '.($registro->maintenance_number ?? $registro->id).'.', [
            'datos' => [
                'id' => $registro->id,
                'numero' => $registro->maintenance_number,
                'estado' => $estado,
                'duracion_minutos' => $registro->duration_minutes,
            ],
            'lista' => $lista = array_values(array_filter($lineas, fn (string $linea) => ! str_ends_with($linea, ': '))),
            'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function trabajadores(array $p, string $intencion): array
    {
        $trabajadores = $this->consultaTrabajadores($p)->get();
        $total = $trabajadores->count();

        if ($total === 0) {
            return $this->respuesta('sin_resultados', $intencion, 'No encontré trabajadores con los filtros indicados.', [
                'datos' => ['total' => 0],
            ]);
        }

        if (isset($p['trabajador']) && $total === 1) {
            return $this->detalleTrabajador($trabajadores->first(), $intencion);
        }

        $descripcion = $this->describirTrabajadores($p);

        if (($p['agrupar_por'] ?? null) !== null) {
            $agrupar = (string) $p['agrupar_por'];
            $grupos = [];

            foreach ($trabajadores as $trabajador) {
                $nombre = match ($agrupar) {
                    'cuadrilla' => $trabajador->crew ?: 'Sin cuadrilla',
                    'cargo' => $trabajador->position ?: 'Sin cargo',
                    default => 'Total',
                };

                $grupos[$nombre] = ($grupos[$nombre] ?? 0) + 1;
            }

            arsort($grupos);

            return $this->respuesta('completado', $intencion, sprintf(
                'Hay %d trabajadores %s, agrupados por %s.',
                $total,
                $descripcion,
                self::ETIQUETAS_AGRUPACION[$agrupar] ?? $agrupar,
            ), [
                'datos' => ['total' => $total, 'grupos' => $grupos],
                'lista' => collect($grupos)->map(fn (int $cantidad, string $nombre) => $nombre.' · '.$this->plural($cantidad, 'trabajador', 'trabajadores'))->values()->all(),
                'navegacion' => ['ruta' => '/trabajadores', 'etiqueta' => 'Abrir trabajadores'],
            ]);
        }

        if (($p['operacion'] ?? null) === 'contar' || $intencion === 'contar_trabajadores') {
            $activos = $trabajadores->where('is_active', true)->count();

            return $this->respuesta('completado', $intencion, sprintf(
                'Hay %d trabajadores %s (%d activos y %d inactivos).',
                $total,
                $descripcion,
                $activos,
                $total - $activos,
            ), ['datos' => ['total' => $total, 'activos' => $activos, 'inactivos' => $total - $activos]]);
        }

        $limite = $this->limite($p);

        return $this->respuesta('completado', $intencion, sprintf(
            'Encontré %d trabajadores %s.%s',
            $total,
            $descripcion,
            $total > $limite ? ' Muestro los primeros '.$limite.'.' : '',
        ), [
            'datos' => ['total' => $total, 'activos' => $trabajadores->where('is_active', true)->count()],
            'lista' => $trabajadores->take($limite)->map(fn (Worker $trabajador) => implode(' · ', array_filter([
                $trabajador->code,
                $trabajador->full_name,
                $trabajador->position,
                $trabajador->crew,
                $trabajador->is_active ? 'Activo' : 'Inactivo',
                $trabajador->maintenance_records_count.' mantenimiento'.($trabajador->maintenance_records_count === 1 ? '' : 's'),
            ])))->values()->all(),
            'navegacion' => ['ruta' => '/trabajadores', 'etiqueta' => 'Abrir trabajadores'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detalleTrabajador(Worker $trabajador, string $intencion): array
    {
        $registros = $trabajador->maintenanceRecords()->with('park')->get();
        $finalizados = $registros->whereIn('status', OperationalScope::TERMINAL_STATUSES);
        $pendientes = $registros->filter(fn (MaintenanceRecord $registro) => in_array($registro->operationalStatus($this->hoy()), ['vencido', 'pendiente', 'proximo'], true));

        $lineas = [
            'Código: '.($trabajador->code ?? 'sin código'),
            'Documento: '.($trabajador->document_number ?: 'sin documento'),
            'Cargo: '.($trabajador->position ?: 'sin cargo'),
            'Cuadrilla: '.($trabajador->crew ?: 'sin cuadrilla'),
            'Teléfono: '.($trabajador->phone ?: 'sin teléfono'),
            'Estado: '.($trabajador->is_active ? 'Activo' : 'Inactivo'),
            'Mantenimientos registrados: '.$registros->count(),
            'Finalizados: '.$finalizados->count(),
            'Pendientes o vencidos: '.$pendientes->count(),
            'Horas registradas: '.round($registros->sum('duration_minutes') / 60, 1).' h',
            'Parques atendidos: '.(collect($registros->pluck('park.name'))->filter()->unique()->implode(', ') ?: 'sin registros'),
            'Tipos de trabajo: '.(collect($registros->pluck('maintenance_type'))->filter()->unique()->implode(', ') ?: 'sin registros'),
        ];

        if ($trabajador->observations) {
            $lineas[] = 'Observaciones: '.$trabajador->observations;
        }

        return $this->respuesta('completado', $intencion, 'Datos de '.$trabajador->full_name.'.', [
            'datos' => [
                'id' => $trabajador->id,
                'nombre' => $trabajador->full_name,
                'total' => $registros->count(),
                'finalizados' => $finalizados->count(),
            ],
            'lista' => $lineas,
            'navegacion' => ['ruta' => '/trabajadores', 'etiqueta' => 'Abrir trabajadores'],
        ]);
    }

    /**
     * Alertas operativas y parques al día.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function alertas(array $p): array
    {
        $hoy = $this->hoy();
        $inicioHoy = $hoy->toDateTimeString();
        $inicioManana = $hoy->copy()->addDay()->toDateTimeString();
        $texto = (string) ($p['texto'] ?? '');

        $vencidos = OperationalScope::overdue($this->consultaBase($p), $inicioHoy)->count();
        $paraHoy = OperationalScope::dueToday($this->consultaBase($p), $inicioHoy, $inicioManana)->count();
        $proximos = OperationalScope::upcoming($this->consultaBase($p), $inicioManana)->count();
        $enProceso = OperationalScope::inProgress($this->consultaBase($p))->count();

        $datos = [
            'vencidos' => $vencidos,
            'para_hoy' => $paraHoy,
            'proximos' => $proximos,
            'en_proceso' => $enProceso,
            'total' => $vencidos + $paraHoy + $proximos + $enProceso,
        ];

        if (preg_match('/\bal dia\b|\bal día\b|\bsin alertas\b/u', $texto) === 1) {
            $atendidos = MaintenanceRecord::query()
                ->whereIn('status', OperationalScope::IN_PROGRESS_STATUSES)
                ->orWhere(fn (Builder $pendiente) => OperationalScope::pendingBase($pendiente)->where('scheduled_date', '<', $inicioManana))
                ->pluck('park_id')
                ->filter()
                ->unique();

            $parques = $this->consultaParques($p)->whereNotIn('id', $atendidos)->orderBy('name')->get();

            return $this->respuesta('completado', 'consultar_alertas', sprintf(
                'Hay %d parques al día (sin vencidos, pendientes de hoy, próximos ni trabajos en proceso).',
                $parques->count(),
            ), [
                'datos' => ['total' => $parques->count(), 'al_dia' => $parques->count()],
                'lista' => $parques->map(fn (Park $parque) => $this->lineaParque($parque))->values()->all(),
                'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
            ]);
        }

        $detalle = [];

        foreach ([
            'Vencido' => OperationalScope::overdue($this->consultaBase($p), $inicioHoy),
            'Para hoy' => OperationalScope::dueToday($this->consultaBase($p), $inicioHoy, $inicioManana),
            'Próximo' => OperationalScope::upcoming($this->consultaBase($p), $inicioManana),
            'En proceso' => OperationalScope::inProgress($this->consultaBase($p)),
        ] as $etiqueta => $consulta) {
            foreach ((clone $consulta)->orderBy('scheduled_date')->limit(5)->get() as $registro) {
                $detalle[] = $etiqueta.' · '.$this->lineaMantenimiento($registro, $hoy, $p);
            }
        }

        if ($datos['total'] === 0) {
            return $this->respuesta('sin_resultados', 'consultar_alertas', 'No hay alertas operativas pendientes.', ['datos' => $datos]);
        }

        return $this->respuesta('completado', 'consultar_alertas', sprintf(
            'Alertas: %d vencidas, %d para hoy, %d próximas y %d en proceso.',
            $vencidos,
            $paraHoy,
            $proximos,
            $enProceso,
        ), [
            'datos' => $datos,
            'lista' => array_slice($detalle, 0, $this->limite($p)),
            'navegacion' => ['ruta' => '/mantenimientos', 'etiqueta' => 'Abrir mantenimientos'],
        ]);
    }

    /**
     * Programación periódica y reglas del sistema.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function programacion(array $p): array
    {
        if (isset($p['parque'])) {
            $consulta = ParkSchedule::query()
                ->with('park')
                ->where('park_id', $p['parque']['id'])
                ->when($p['tipo_mantenimiento'] ?? null, fn (Builder $query, string $tipo) => $query->where('maintenance_type', $tipo))
                ->orderBy('maintenance_type');

            $programaciones = $consulta->get();

            if ($programaciones->isEmpty()) {
                return $this->respuesta('sin_resultados', 'consultar_programacion', 'Ese parque no tiene programaciones periódicas registradas.', [
                    'datos' => ['total' => 0],
                ]);
            }

            return $this->respuesta('completado', 'consultar_programacion', 'Programación de '.$p['parque']['nombre'].'.', [
                'datos' => ['total' => $programaciones->count()],
                'lista' => $programaciones->map(fn (ParkSchedule $programacion) => implode(' · ', array_filter([
                    $programacion->maintenance_type,
                    $programacion->is_active ? 'Activa' : 'Inactiva',
                    'Cada '.$programacion->frequency_days.' días',
                    'Última ejecución: '.($programacion->last_executed_at?->format('d/m/Y') ?? 'sin registro'),
                    'Próxima fecha: '.($programacion->next_scheduled_date?->format('d/m/Y') ?? 'por definir'),
                ])))->values()->all(),
                'navegacion' => ['ruta' => '/parques', 'etiqueta' => 'Abrir gestión de parques'],
            ]);
        }

        $reglas = MaintenanceRule::query()
            ->when($p['tipo_mantenimiento'] ?? null, fn (Builder $query, string $tipo) => $query->where('maintenance_type', $tipo))
            ->orderBy('green_area_type')
            ->orderBy('maintenance_type')
            ->get();

        if ($reglas->isEmpty()) {
            return $this->respuesta('sin_resultados', 'consultar_programacion', 'No hay reglas de mantenimiento registradas.');
        }

        return $this->respuesta('completado', 'consultar_programacion', 'Reglas de frecuencia registradas en el sistema.', [
            'datos' => ['total' => $reglas->count()],
            'lista' => $reglas->map(fn (MaintenanceRule $regla) => implode(' · ', array_filter([
                $regla->green_area_type,
                $regla->maintenance_type,
                'Cada '.$regla->frequency_days.' días',
                'Alerta '.$regla->advance_days.' días antes',
                $regla->is_active ? 'Activa' : 'Inactiva',
            ])))->values()->all(),
            'navegacion' => ['ruta' => '/configuracion', 'etiqueta' => 'Abrir configuración'],
        ]);
    }

    /**
     * Motivos de retraso y documentos de respaldo.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function justificaciones(array $p): array
    {
        $consulta = MaintenanceAudit::query()
            ->with(['maintenance.park', 'maintenance.worker', 'user'])
            ->where('event', 'overdue_justification')
            ->when($p['desde'] ?? null, fn (Builder $query, string $desde) => $p['fecha_registro'] ?? false
                ? $query->whereDate('created_at', '>=', $desde)
                : $query->whereDate('scheduled_date', '>=', $desde))
            ->when($p['hasta'] ?? null, fn (Builder $query, string $hasta) => $p['fecha_registro'] ?? false
                ? $query->whereDate('created_at', '<=', $hasta)
                : $query->whereDate('scheduled_date', '<=', $hasta))
            ->when($p['parque'] ?? null, fn (Builder $query, array $parque) => $query
                ->whereHas('maintenance', fn (Builder $registro) => $registro->where('park_id', $parque['id'])))
            ->when($p['trabajador'] ?? null, fn (Builder $query, array $trabajador) => $query
                ->whereHas('maintenance', fn (Builder $registro) => $registro
                    ->where('worker_id', $trabajador['id'])
                    ->orWhere('responsible_worker', $trabajador['nombre'])))
            ->when($p['tipo_mantenimiento'] ?? null, fn (Builder $query, string $tipo) => $query
                ->whereHas('maintenance', fn (Builder $registro) => $registro->where('maintenance_type', $tipo)))
            ->when($p['numero_orden'] ?? null, fn (Builder $query, string $numero) => $query
                ->whereHas('maintenance', fn (Builder $registro) => $registro->where('maintenance_number', $numero)))
            ->when($p['orden_id'] ?? null, fn (Builder $query, int $id) => $query
                ->where('maintenance_record_id', $id))
            ->when(isset($p['con_documento']), fn (Builder $query) => $p['con_documento']
                ? $query->whereNotNull('document_path')
                : $query->whereNull('document_path'))
            ->when($p['busqueda_texto'] ?? null, fn (Builder $query, string $texto) => $query->where('reason', 'like', '%'.$texto.'%'))
            ->latest();

        $total = (clone $consulta)->count();

        if ($total === 0) {
            return $this->respuesta('sin_resultados', 'consultar_justificaciones', 'No encontré motivos de retraso con los filtros indicados.', [
                'datos' => ['total' => 0],
            ]);
        }

        if (($p['operacion'] ?? null) === 'contar') {
            return $this->respuesta('completado', 'consultar_justificaciones', "Hay {$total} justificaciones de retraso registradas.", [
                'datos' => ['total' => $total],
            ]);
        }

        $limite = $this->limite($p);
        $auditorias = $consulta->limit($limite)->get();

        $datos = [
            'total' => $total,
            'con_documento' => MaintenanceAudit::query()
                ->where('event', 'overdue_justification')
                ->whereNotNull('document_path')
                ->count(),
        ];

        $respuesta = $this->respuesta('completado', 'consultar_justificaciones', sprintf(
            'Encontré %d motivos de retraso registrados%s.',
            $total,
            $total > $limite ? '; muestro los primeros '.$limite : '',
        ), [
            'datos' => $datos,
            'lista' => $auditorias->map(fn (MaintenanceAudit $auditoria) => implode(' · ', array_filter([
                $auditoria->created_at?->format('d/m/Y'),
                $auditoria->maintenance?->maintenance_number,
                $auditoria->maintenance?->park?->name,
                $auditoria->reason ?: 'Sin texto (solo documento)',
                $auditoria->document_name ? 'Documento: '.$auditoria->document_name : 'Sin documento',
                'Registrado por '.($auditoria->user?->name ?? 'usuario no disponible'),
            ])))->values()->all(),
            'navegacion' => ['ruta' => '/reportes', 'etiqueta' => 'Abrir reportes'],
        ]);

        if ($total === 1 && $auditorias->first()->document_path
            && preg_match('/\b(?:documento|respaldo|adjunto)\b/u', (string) ($p['texto'] ?? '')) === 1) {
            $auditoria = $auditorias->first();
            $respuesta['archivo'] = [
                'nombre' => $auditoria->document_name ?: 'documento-de-respaldo',
                'url' => route('maintenances.audit-document', $auditoria, false),
            ];
        }

        return $respuesta;
    }

    /**
     * Catálogos e información institucional.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function catalogos(array $p): array
    {
        $texto = (string) ($p['texto'] ?? '');

        if (preg_match('/\b(?:municipalidad|organizacion|institucional|area responsable|responsable del area|ano de gestion|pie de documento|direccion)\b/u', $texto) === 1) {
            $institucion = InstitutionalSetting::query()->first();

            return $this->respuesta('completado', 'consultar_catalogos', 'Información institucional registrada.', [
                'lista' => array_values(array_filter([
                    'Organización: '.($institucion?->organization_name ?? 'sin registro'),
                    'Área responsable: '.($institucion?->unit_name ?? 'sin registro'),
                    'Dirección: '.($institucion?->address ?? 'sin registro'),
                    'Responsable: '.($institucion?->manager_name ?? 'sin registro'),
                    'Año de gestión: '.($institucion?->management_year ?? 'sin registro'),
                    'Pie de documento: '.($institucion?->document_footer ?? 'sin registro'),
                ])),
                'navegacion' => ['ruta' => '/configuracion', 'etiqueta' => 'Abrir configuración'],
            ]);
        }

        if (preg_match('/\b(?:area verde|areas verdes)\b/u', $texto) === 1) {
            $tipos = GreenAreaType::query()->orderBy('name')->get();

            if ($tipos->isEmpty()) {
                $tipos = Park::query()->whereNotNull('green_area_type')->distinct()->orderBy('green_area_type')->pluck('green_area_type');
            }

            return $this->respuesta('completado', 'consultar_catalogos', 'Tipos de área verde registrados: '.count($tipos).'.', [
                'lista' => collect($tipos)->map(fn ($tipo) => is_object($tipo)
                    ? $tipo->name.' · '.($tipo->is_active ? 'Activo' : 'Inactivo')
                    : (string) $tipo)->values()->all(),
                'navegacion' => ['ruta' => '/configuracion', 'etiqueta' => 'Abrir configuración'],
            ]);
        }

        if (preg_match('/\bregla|frecuencia\b/u', $texto) === 1) {
            return $this->programacion($p);
        }

        $tipos = MaintenanceType::query()->orderBy('name')->get();

        if ($tipos->isEmpty()) {
            $tipos = MaintenanceRecord::query()
                ->whereNotNull('maintenance_type')
                ->distinct()
                ->orderBy('maintenance_type')
                ->pluck('maintenance_type');
        }

        return $this->respuesta('completado', 'consultar_catalogos', 'Tipos de mantenimiento registrados: '.count($tipos).'.', [
            'lista' => collect($tipos)->map(fn ($tipo) => is_object($tipo)
                ? $tipo->name.' · '.($tipo->is_active ? 'Activo' : 'Inactivo')
                : (string) $tipo)->values()->all(),
            'navegacion' => ['ruta' => '/configuracion', 'etiqueta' => 'Abrir configuración'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function filtrosDeHistorial(array $p): bool
    {
        foreach (['tipo_mantenimiento', 'origen', 'duracion_min', 'duracion_max', 'con_documento', 'con_foto', 'busqueda_texto', 'retraso_min'] as $clave) {
            if (isset($p[$clave])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mantenimientos filtrados por parque y trabajador, sin estado ni período.
     *
     * @param  array<string, mixed>  $p
     */
    private function consultaBase(array $p): Builder
    {
        $consulta = MaintenanceRecord::query()->with(['park:id,code,name,zone', 'worker:id,first_name,last_name', 'schedule:id,frequency_days']);

        if (isset($p['parque'])) {
            $consulta->where('park_id', $p['parque']['id']);
        }

        if (! empty($p['parques'])) {
            $consulta->whereIn('park_id', array_column($p['parques'], 'id'));
        }

        if (isset($p['trabajador'])) {
            $consulta->where(fn (Builder $trabajador) => $trabajador
                ->where('worker_id', $p['trabajador']['id'])
                ->orWhere('responsible_worker', $p['trabajador']['nombre']));
        }

        return $consulta;
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function consultaMantenimientos(array $p, ?string $estado, bool $historial = false): Builder
    {
        $consulta = $this->consultaBase($p);
        $this->aplicarFiltrosMantenimiento($consulta, $p);

        $hoy = $this->hoy();
        $inicioHoy = $hoy->toDateTimeString();
        $inicioManana = $hoy->copy()->addDay()->toDateTimeString();
        $desde = $p['desde'] ?? null;
        $hasta = $p['hasta'] ?? null;

        $entre = function (Builder $consulta, string $columna) use ($desde, $hasta): Builder {
            if ($desde && $hasta) {
                $consulta->whereDate($columna, '>=', $desde)->whereDate($columna, '<=', $hasta);
            } elseif ($desde) {
                $consulta->whereDate($columna, '>=', $desde);
            } elseif ($hasta) {
                $consulta->whereDate($columna, '<=', $hasta);
            }

            return $consulta;
        };

        switch ($estado) {
            case 'por_atender':
                OperationalScope::pendingBase($consulta);

                if ($desde && $hasta) {
                    // "Faltan" incluye los pendientes futuros dentro del período pedido.
                    $entre($consulta, 'scheduled_date');
                } else {
                    $consulta->where('scheduled_date', '<', $inicioManana);
                }

                return $consulta->orderBy($this->orden($p, 'scheduled_date'))->orderBy('id');

            case 'vencido':
                OperationalScope::overdue($consulta, $inicioHoy);

                return $entre($consulta, 'scheduled_date')->orderBy($this->orden($p, 'scheduled_date'))->orderBy('id');

            case 'proximo':
                OperationalScope::upcoming($consulta, $inicioManana);

                return $entre($consulta, 'scheduled_date')->orderBy($this->orden($p, 'scheduled_date'))->orderBy('id');

            case 'en_proceso':
                OperationalScope::inProgress($consulta);

                if ($desde || $hasta) {
                    $consulta->where(function (Builder $fechas) use ($entre) {
                        $fechas->where(fn (Builder $a) => $entre($a, 'execution_date'))
                            ->orWhere(fn (Builder $b) => $entre($b, 'scheduled_date'));
                    });
                }

                return $consulta->orderBy($this->orden($p, 'scheduled_date'))->orderBy('id');

            case 'finalizado':
                OperationalScope::finished($consulta);

                if ($desde || $hasta) {
                    $entre($consulta, 'execution_date');
                }

                return $consulta->orderByDesc('execution_date')->orderByDesc('id');

            case 'cancelado':
                $consulta->where('status', 'cancelado');

                return $entre($consulta, 'scheduled_date')->orderByDesc('scheduled_date')->orderByDesc('id');
        }

        if ($desde || $hasta) {
            // Mismo criterio de fechas que el módulo de reportes (base "operativa").
            MaintenanceReportFilters::apply($consulta, MaintenanceReportFilters::defaults([
                'date_from' => $desde ?? '',
                'date_to' => $hasta ?? '',
            ]));

            return $consulta->orderByDesc('scheduled_date')->orderByDesc('id');
        }

        if ($historial) {
            return $consulta->orderByDesc('scheduled_date')->orderByDesc('id');
        }

        OperationalScope::operational($consulta, $inicioManana);

        return $consulta->orderBy($this->orden($p, 'scheduled_date'))->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function aplicarFiltrosMantenimiento(Builder $consulta, array $p): void
    {
        $consulta
            ->when($p['tipo_mantenimiento'] ?? null, fn (Builder $query, string $tipo) => $query->where('maintenance_type', $tipo))
            ->when($p['origen'] ?? null, fn (Builder $query, string $origen) => $query
                ->where('is_extraordinary', $origen === 'extraordinario'))
            ->when($p['asignacion'] ?? null, function (Builder $query, string $asignacion) {
                if ($asignacion === 'asignado') {
                    $query->where(function (Builder $asignado) {
                        $asignado->whereNotNull('worker_id')
                            ->orWhere(function (Builder $responsable) {
                                $responsable->whereNotNull('responsible_worker')
                                    ->where('responsible_worker', '<>', '')
                                    ->where('responsible_worker', '<>', 'Por asignar');
                            });
                    });

                    return;
                }

                $query->whereNull('worker_id')
                    ->where(function (Builder $responsable) {
                        $responsable->whereNull('responsible_worker')
                            ->orWhere('responsible_worker', '')
                            ->orWhere('responsible_worker', 'Por asignar');
                    });
            })
            ->when(isset($p['duracion_min']), fn (Builder $query) => $query->where('duration_minutes', '>=', (int) $p['duracion_min']))
            ->when(isset($p['duracion_max']), fn (Builder $query) => $query->where('duration_minutes', '<=', (int) $p['duracion_max']))
            ->when($p['retraso_min'] ?? null, function (Builder $query, int $dias) {
                $limite = $this->hoy()->subDays($dias)->toDateString();

                $query->whereNotIn('status', [...OperationalScope::TERMINAL_STATUSES, ...OperationalScope::IN_PROGRESS_STATUSES, 'cancelado'])
                    ->whereDate('scheduled_date', '<=', $limite);
            })
            ->when(isset($p['con_documento']), fn (Builder $query) => $p['con_documento']
                ? $query->whereNotNull('document_url')
                : $query->whereNull('document_url'))
            ->when(isset($p['con_foto']), fn (Builder $query) => $p['con_foto']
                ? $query->whereNotNull('photo_after_url')
                : $query->whereNull('photo_after_url'))
            ->when($p['busqueda_texto'] ?? null, function (Builder $query, string $texto) {
                $like = '%'.$texto.'%';

                $query->where(function (Builder $busqueda) use ($like) {
                    $busqueda->where('observations', 'like', $like)
                        ->orWhere('work_description', 'like', $like)
                        ->orWhere('result', 'like', $like)
                        ->orWhere('initial_observation', 'like', $like)
                        ->orWhere('maintenance_number', 'like', $like);
                });
            })
            ->when($p['trabajador_cuadrilla'] ?? null, fn (Builder $query, string $cuadrilla) => $query
                ->whereHas('worker', fn (Builder $trabajador) => $trabajador->where('crew', $cuadrilla)))
            ->when($p['trabajador_cargo'] ?? null, fn (Builder $query, string $cargo) => $query
                ->whereHas('worker', fn (Builder $trabajador) => $trabajador->where('position', $cargo)))
            ->when($p['numero_orden'] ?? null, fn (Builder $query, string $numero) => $query->where('maintenance_number', $numero))
            ->when($p['orden_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id));
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function consultaParques(array $p): Builder
    {
        $consulta = Park::query();

        if (isset($p['parque'])) {
            $consulta->whereKey($p['parque']['id']);
        }

        if (! empty($p['parques'])) {
            $consulta->whereIn('id', array_column($p['parques'], 'id'));
        }

        $consulta
            ->when($p['agua'] ?? null, fn (Builder $query, string $agua) => $query->where('has_water_point', $agua === 'con'))
            ->when($p['agua_estado'] ?? null, fn (Builder $query, string $estado) => $query->where('water_point_status', $estado))
            ->when($p['agua_abastecimiento'] ?? null, fn (Builder $query, string $tipo) => $query->where('water_supply_type', $tipo))
            ->when($p['parque_tipo'] ?? null, fn (Builder $query, string $tipo) => $query->where('green_area_type', $tipo))
            ->when($p['parque_zona'] ?? null, fn (Builder $query, string $zona) => $query->where('zone', $zona))
            ->when($p['parque_conservacion'] ?? null, fn (Builder $query, string $estado) => $query->where('conservation_status', $estado))
            ->when(isset($p['parque_activo']), fn (Builder $query) => $query->where('is_active', (bool) $p['parque_activo']))
            ->when(isset($p['superficie_min']), fn (Builder $query) => $query->whereRaw('CAST(COALESCE(green_area, area, 0) AS REAL) >= ?', [(float) $p['superficie_min']]))
            ->when(isset($p['superficie_max']), fn (Builder $query) => $query->whereRaw('CAST(COALESCE(green_area, area, 0) AS REAL) <= ?', [(float) $p['superficie_max']]))
            ->when(isset($p['con_foto']), fn (Builder $query) => $p['con_foto']
                ? $query->whereNotNull('photo_url')->where('photo_url', '<>', '')
                : $query->where(fn (Builder $sin) => $sin->whereNull('photo_url')->orWhere('photo_url', '')))
            ->when(isset($p['sin_foto']), fn (Builder $query) => $query->where(fn (Builder $sin) => $sin->whereNull('photo_url')->orWhere('photo_url', '')))
            ->when(isset($p['con_ubicacion']), fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when(isset($p['sin_ubicacion']), fn (Builder $query) => $query->where(fn (Builder $sin) => $sin->whereNull('latitude')->orWhereNull('longitude')))
            ->when(isset($p['con_observaciones']), fn (Builder $query) => $query
                ->where(fn (Builder $con) => $con->whereNotNull('general_observations')->where('general_observations', '<>', '')))
            ->when(isset($p['sin_observaciones']), fn (Builder $query) => $query
                ->where(fn (Builder $sin) => $sin->whereNull('general_observations')->orWhere('general_observations', '')));

        if (! empty($p['fecha_registro']) && isset($p['desde'], $p['hasta'])) {
            $consulta->whereDate('registration_date', '>=', $p['desde'])->whereDate('registration_date', '<=', $p['hasta']);
        }

        return match ($p['orden'] ?? null) {
            'superficie_desc' => $consulta->orderByRaw('COALESCE(green_area, area, 0) DESC')->orderBy('name'),
            'asc' => $consulta->orderBy('name'),
            'desc' => $consulta->orderByDesc('name'),
            default => $consulta->orderBy('name'),
        };
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function consultaTrabajadores(array $p): Builder
    {
        $consulta = Worker::query()->withCount('maintenanceRecords');

        if (isset($p['trabajador'])) {
            $consulta->whereKey($p['trabajador']['id']);
        }

        $consulta
            ->when(isset($p['trabajador_activo']), fn (Builder $query) => $query->where('is_active', (bool) $p['trabajador_activo']))
            ->when($p['trabajador_cargo'] ?? null, fn (Builder $query, string $cargo) => $query->where('position', $cargo))
            ->when($p['trabajador_cuadrilla'] ?? null, fn (Builder $query, string $cuadrilla) => $query->where('crew', $cuadrilla))
            ->when(! empty($p['sin_trabajos']), fn (Builder $query) => $query->has('maintenanceRecords', '=', 0));

        return $consulta->orderBy('last_name')->orderBy('first_name');
    }

    /**
     * Genera el reporte con el módulo de reportes existente y devuelve su enlace.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function generarReporte(array $p): array
    {
        $modulo = (string) ($p['modulo'] ?? '');
        $formato = $p['formato'] ?? null;

        if (! isset(self::TIPOS_REPORTE[$modulo])) {
            return $this->respuesta('no_reconocido', 'generar_reporte', 'Ese módulo no tiene un reporte disponible. Puedo generar reportes de mantenimientos, parques, trabajadores, historial por parque, motivos de retraso, alertas o puntos de agua.');
        }

        if ($formato === null) {
            return $this->respuesta('requiere_aclaracion', 'generar_reporte', '¿En qué formato lo necesitas: PDF o Excel?', [
                'parametros_pendientes' => ['formato'],
            ]);
        }

        $tipo = self::TIPOS_REPORTE[$modulo];
        $consulta = ['type' => $tipo];
        $notas = [];

        $mantenimiento = in_array($modulo, ['mantenimientos', 'historial', 'justificaciones'], true);
        $estado = $p['estado'] ?? null;
        $estadoReporte = match ($estado) {
            'finalizado', 'vencido', 'proximo', 'en_proceso', 'cancelado' => $estado,
            default => null,
        };

        $filtros = MaintenanceReportFilters::defaults(array_filter([
            'date_from' => $p['desde'] ?? '',
            'date_to' => $p['hasta'] ?? '',
            'status' => $mantenimiento ? ($estadoReporte ?? '') : '',
            'date_basis' => $estado === 'finalizado' ? 'executed' : 'operational',
            'park_id' => isset($p['parque']) ? (string) $p['parque']['id'] : '',
            'park_ids' => ! empty($p['parques']) ? implode(',', array_column($p['parques'], 'id')) : '',
            'worker_id' => isset($p['trabajador']) ? (string) $p['trabajador']['id'] : '',
            'maintenance_type' => $p['tipo_mantenimiento'] ?? '',
            'maintenance_origin' => $p['origen'] ?? '',
            'assignment' => $p['asignacion'] ?? '',
            'duration_min' => $p['duracion_min'] ?? '',
            'duration_max' => $p['duracion_max'] ?? '',
            'retraso_min' => $p['retraso_min'] ?? '',
            'has_document' => isset($p['con_documento']) ? ($p['con_documento'] ? '1' : '0') : '',
            'has_photo' => isset($p['con_foto']) ? ($p['con_foto'] ? '1' : '0') : '',
            'search' => $p['busqueda_texto'] ?? '',
        ], fn ($valor) => $valor !== ''));

        // Filtros propios de parques y trabajadores, admitidos por el módulo de reportes.
        $filtrosParque = array_filter([
            'park_ids' => ! empty($p['parques']) ? implode(',', array_column($p['parques'], 'id')) : '',
            'park_type' => $p['parque_tipo'] ?? '',
            'park_zone' => $p['parque_zona'] ?? '',
            'park_conservation' => $p['parque_conservacion'] ?? '',
            'park_active' => isset($p['parque_activo']) ? ($p['parque_activo'] ? '1' : '0') : '',
            'park_water' => $p['agua'] ?? '',
            'park_water_status' => $p['agua_estado'] ?? '',
            'park_supply' => $p['agua_abastecimiento'] ?? '',
            'park_area_min' => isset($p['superficie_min']) ? (string) $p['superficie_min'] : '',
            'park_area_max' => isset($p['superficie_max']) ? (string) $p['superficie_max'] : '',
            'park_photo' => isset($p['con_foto']) ? '1' : (isset($p['sin_foto']) ? '0' : ''),
            'park_location' => isset($p['con_ubicacion']) ? '1' : (isset($p['sin_ubicacion']) ? '0' : ''),
            'park_observations' => isset($p['con_observaciones']) ? '1' : (isset($p['sin_observaciones']) ? '0' : ''),
            'park_registered_from' => ! empty($p['fecha_registro']) ? ($p['desde'] ?? '') : '',
            'park_registered_to' => ! empty($p['fecha_registro']) ? ($p['hasta'] ?? '') : '',
            'worker_active' => isset($p['trabajador_activo']) ? ($p['trabajador_activo'] ? '1' : '0') : '',
            'worker_position' => $p['trabajador_cargo'] ?? '',
            'worker_crew' => $p['trabajador_cuadrilla'] ?? '',
            'worker_without_records' => ! empty($p['sin_trabajos']) ? '1' : '',
        ], fn ($valor) => $valor !== '');

        $consulta += array_filter([...$filtros, ...$filtrosParque], fn ($valor) => $valor !== '');

        $registros = match ($modulo) {
            'mantenimientos', 'historial' => MaintenanceReportFilters::apply(MaintenanceRecord::query(), $filtros)->count(),
            'justificaciones' => MaintenanceAudit::query()
                ->where('event', 'overdue_justification')
                ->whereHas('maintenance', fn (Builder $registro) => MaintenanceReportFilters::apply($registro, $filtros))
                ->count(),
            'parques', 'agua' => $this->consultaParques($p)->count(),
            'trabajadores' => $this->consultaTrabajadores($p)->count(),
            'alertas' => MaintenanceRecord::query()->count(),
            default => 0,
        };

        $unidad = match ($modulo) {
            'mantenimientos' => 'mantenimientos',
            'historial' => 'mantenimientos en el historial',
            'justificaciones' => 'justificaciones de retraso',
            'parques' => 'parques',
            'agua' => 'parques en el reporte de agua',
            'trabajadores' => 'trabajadores',
            'alertas' => 'registros de alerta',
            default => $modulo,
        };

        if ($modulo === 'alertas') {
            $notas[] = 'El reporte de alertas no aplica períodos ni estados; reúne las alertas operativas actuales.';
        }

        if (in_array($modulo, ['parques', 'agua'], true)) {
            $notas[] = 'El reporte de parques no admite período ni estado; se aplican los filtros de la ficha del parque.';
        }

        if ($modulo === 'trabajadores') {
            $notas[] = 'El reporte de trabajadores no admite período; se aplican los filtros del personal.';
        }

        if ($registros === 0) {
            return $this->respuesta('sin_resultados', 'generar_reporte', "No hay {$unidad} para ese reporte, así que no generé ningún documento.", [
                'datos' => $this->datosReporte($modulo, $p, 0),
            ]);
        }

        try {
            $this->verificador->verificar($formato === 'pdf' ? 'pdf' : 'csv', $consulta);
        } catch (Throwable $e) {
            Log::error('asistente.reporte_fallido', ['clase' => $e::class, 'archivo' => basename($e->getFile()), 'linea' => $e->getLine()]);

            return $this->respuesta('error', 'generar_reporte', 'No pude generar el documento en este momento. Inténtalo de nuevo más tarde.');
        }

        $extension = $formato === 'pdf' ? 'pdf' : 'csv';
        $ruta = route($formato === 'pdf' ? 'reports.pdf' : 'reports.csv', $consulta, false);

        if ($formato === 'excel') {
            $notas[] = 'El archivo es un CSV compatible con Excel.';
        }

        $mensaje = trim(sprintf('El reporte está disponible: %d %s. %s', $registros, $unidad, implode(' ', $notas)));

        return $this->respuesta('completado', 'generar_reporte', $mensaje, [
            'datos' => $this->datosReporte($modulo, $p, $registros),
            'archivo' => [
                'nombre' => 'reporte-'.$modulo.'-'.now()->format('Y-m-d').'.'.$extension,
                'url' => $ruta,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function datosReporte(string $modulo, array $p, int $registros): array
    {
        return array_filter([
            'modulo' => $modulo,
            'periodo' => in_array($modulo, ['mantenimientos', 'historial', 'justificaciones'], true) ? ($p['periodo'] ?? null) : null,
            'desde' => in_array($modulo, ['mantenimientos', 'historial', 'justificaciones'], true) ? ($p['desde'] ?? null) : null,
            'hasta' => in_array($modulo, ['mantenimientos', 'historial', 'justificaciones'], true) ? ($p['hasta'] ?? null) : null,
            'estado' => in_array($modulo, ['mantenimientos', 'historial', 'justificaciones'], true) ? ($p['estado'] ?? null) : null,
            'formato' => $p['formato'],
            'registros' => $registros,
        ], fn ($valor) => $valor !== null);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function describir(array $p, ?string $estado): string
    {
        $partes = [match ($estado) {
            'por_atender' => 'por atender',
            'vencido' => 'vencidos',
            'proximo' => 'próximos',
            'en_proceso' => 'en proceso',
            'finalizado' => 'realizados',
            'cancelado' => 'cancelados',
            default => '',
        }];

        if (isset($p['tipo_mantenimiento'])) {
            $partes[] = 'de '.$p['tipo_mantenimiento'];
        }

        if (isset($p['origen'])) {
            $partes[] = $p['origen'] === 'extraordinario' ? 'adicionales' : 'de programación periódica';
        }

        if (isset($p['periodo'])) {
            $partes[] = $this->etiquetaPeriodo($p);
        }

        if (isset($p['parque'])) {
            $partes[] = 'del parque '.$p['parque']['nombre'];
        }

        if (! empty($p['parques'])) {
            $partes[] = 'de '.collect($p['parques'])->pluck('nombre')->implode(' y ');
        }

        if (isset($p['trabajador'])) {
            $partes[] = 'de '.$p['trabajador']['nombre'];
        }

        if (isset($p['duracion_min'])) {
            $partes[] = 'de al menos '.round($p['duracion_min'] / 60, 1).' h';
        }

        if (isset($p['duracion_max'])) {
            $partes[] = 'de hasta '.round($p['duracion_max'] / 60, 1).' h';
        }

        if (isset($p['retraso_min'])) {
            $partes[] = 'con más de '.$p['retraso_min'].' días de atraso';
        }

        return trim(implode(' ', array_filter($partes))) ?: 'en la bandeja operativa';
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function describirParques(array $p): string
    {
        $partes = [];

        if (isset($p['agua'])) {
            $partes[] = $p['agua'] === 'con' ? 'con punto de agua' : 'sin punto de agua';
        }

        if (isset($p['agua_estado'])) {
            $partes[] = 'con punto de agua '.mb_strtolower($p['agua_estado']);
        }

        if (isset($p['agua_abastecimiento'])) {
            $partes[] = 'abastecidos por '.mb_strtolower($p['agua_abastecimiento']);
        }

        if (isset($p['parque_tipo'])) {
            $partes[] = 'de tipo '.$p['parque_tipo'];
        }

        if (isset($p['parque_zona'])) {
            $partes[] = 'de la zona '.$p['parque_zona'];
        }

        if (isset($p['parque_conservacion'])) {
            $partes[] = 'en estado '.$p['parque_conservacion'];
        }

        if (isset($p['parque_activo'])) {
            $partes[] = $p['parque_activo'] ? 'activos' : 'inactivos';
        }

        if (isset($p['superficie_min'])) {
            $partes[] = 'con al menos '.$p['superficie_min'].' m² de área verde';
        }

        if (isset($p['superficie_max'])) {
            $partes[] = 'con hasta '.$p['superficie_max'].' m² de área verde';
        }

        return $partes === [] ? 'registrados' : implode(' ', $partes);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function describirTrabajadores(array $p): string
    {
        $partes = [];

        if (isset($p['trabajador_activo'])) {
            $partes[] = $p['trabajador_activo'] ? 'activos' : 'inactivos';
        }

        if (isset($p['trabajador_cargo'])) {
            $partes[] = 'con cargo '.$p['trabajador_cargo'];
        }

        if (isset($p['trabajador_cuadrilla'])) {
            $partes[] = 'de la cuadrilla '.$p['trabajador_cuadrilla'];
        }

        if (! empty($p['sin_trabajos'])) {
            $partes[] = 'sin mantenimientos registrados';
        }

        return $partes === [] ? 'registrados' : implode(' ', $partes);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function etiquetaPeriodo(array $p): string
    {
        $formato = fn (string $fecha) => Carbon::parse($fecha)->format('d/m/Y');

        return match ($p['periodo']) {
            'hoy' => 'de hoy',
            'ayer' => 'de ayer',
            'anteayer' => 'de anteayer',
            'manana' => 'de mañana',
            'pasado_manana' => 'de pasado mañana',
            'semana_actual' => "de esta semana ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'semana_pasada' => "de la semana pasada ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'proxima_semana' => "de la próxima semana ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'mes_actual' => 'de este mes',
            'mes_pasado' => 'del mes pasado',
            'proximo_mes' => "del próximo mes ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'anio_actual' => "de lo que va del año ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'anio_pasado' => 'del año pasado',
            'proximo_anio' => "del próximo año ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'quincena_actual' => "de esta quincena ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'trimestre_actual' => "de este trimestre ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'semestre_actual' => "de este semestre ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'todo' => 'de todo el historial',
            'mes_nombre' => 'de '.$this->nombreMes($p['desde'])." ({$formato($p['desde'])} al {$formato($p['hasta'])})",
            'fecha' => "del {$formato($p['desde'])}",
            default => "del {$formato($p['desde'])} al {$formato($p['hasta'])}",
        };
    }

    private function nombreMes(string $fecha): string
    {
        $numero = (int) Carbon::parse($fecha)->format('n');
        $nombre = array_search($numero, Diccionario::MESES, true);

        return is_string($nombre) ? $nombre : '';
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function claveAgrupacion(MaintenanceRecord $registro, string $agrupar): string
    {
        return match ($agrupar) {
            'tipo' => $registro->maintenance_type ?: 'Sin tipo',
            'parque' => $registro->park?->name ?? 'Sin parque',
            'trabajador' => $registro->worker?->full_name ?? $registro->responsible_worker ?? 'Por asignar',
            'estado' => self::ETIQUETAS_ESTADO[$registro->operationalStatus($this->hoy())] ?? ucfirst((string) $registro->status),
            'zona' => $registro->park?->zone ?: 'Sin zona',
            'mes' => $registro->scheduled_date?->format('m/Y') ?? 'Sin fecha',
            'cuadrilla' => $registro->worker?->crew ?? 'Sin cuadrilla',
            'cargo' => $registro->worker?->position ?? 'Sin cargo',
            'conservacion' => ucfirst((string) ($registro->park?->conservation_status ?? 'Sin estado')),
            default => $registro->maintenance_type ?: 'Sin tipo',
        };
    }

    /**
     * @param  Collection<int, Park>  $parques
     * @return array<string, int>
     */
    private function agruparParques(Collection $parques, string $agrupar): array
    {
        $grupos = [];

        foreach ($parques as $parque) {
            $nombre = match ($agrupar) {
                'zona' => $parque->zone ?: 'Sin zona',
                'tipo' => $parque->green_area_type ?: 'Sin tipo',
                'conservacion' => ucfirst((string) ($parque->conservation_status ?? 'Sin estado')),
                default => 'Total',
            };

            $grupos[$nombre] = ($grupos[$nombre] ?? 0) + 1;
        }

        arsort($grupos);

        return $grupos;
    }

    private function lineaMantenimiento(MaintenanceRecord $registro, Carbon $hoy, array $p = []): string
    {
        $estado = $registro->operationalStatus($hoy);
        $programada = $registro->scheduled_date?->format('d/m/Y') ?? 'sin fecha';

        $detalle = match ($estado) {
            'finalizado' => 'Realizado '.($registro->execution_date?->format('d/m/Y') ?? 'sin fecha'),
            'vencido' => 'Programado '.$programada.' (vencido hace '.abs((int) $hoy->diffInDays($registro->scheduled_date)).' días)',
            default => 'Programado '.$programada.' · '.(self::ETIQUETAS_ESTADO[$estado] ?? ucfirst($estado)),
        };

        $extras = [];

        if (($p['agrupar_por'] ?? null) !== null || isset($p['duracion_min']) || isset($p['duracion_max'])) {
            $extras[] = $registro->duration_minutes ? round($registro->duration_minutes / 60, 1).' h' : 'sin duración';
        }

        if (isset($p['con_documento'])) {
            $extras[] = $registro->document_name ? 'Documento: '.$registro->document_name : 'Sin documento';
        }

        return implode(' · ', array_filter([
            $registro->maintenance_number ?? sprintf('PM-%06d', $registro->id),
            $registro->park?->name ?? 'Sin parque',
            $registro->maintenance_type,
            $detalle,
            ...$extras,
        ]));
    }

    private function lineaParque(Park $parque, bool $incluirAgua = false): string
    {
        return implode(' · ', array_filter([
            $parque->code,
            $parque->name,
            $parque->green_area_type,
            $parque->zone ?: $parque->address,
            ucfirst((string) ($parque->conservation_status ?? '')),
            $parque->is_active ? 'Activo' : 'Inactivo',
            $incluirAgua ? ($parque->has_water_point ? 'Punto de agua: '.(string) ($parque->water_point_status ?: 'registrado') : 'Sin punto de agua') : null,
            $parque->green_area !== null ? (float) $parque->green_area.' m²' : null,
        ]));
    }

    private function lineaAgua(Park $parque): string
    {
        return implode(' · ', array_filter([
            $parque->code,
            $parque->name,
            $parque->zone ?: $parque->address,
            $parque->has_water_point ? 'Con punto de agua' : 'Sin punto de agua',
            $parque->has_water_point ? ($parque->water_point_status ?: 'sin estado') : null,
            $parque->has_water_point ? ($parque->water_supply_type ?: 'sin abastecimiento registrado') : null,
        ]));
    }

    private function superficie(mixed $valor): string
    {
        return $valor !== null ? (float) $valor.' m²' : 'sin registro';
    }

    /**
     * @param  Collection<int, Park>  $parques
     * @return array{total: int, activos: int, inactivos: int}
     */
    private function conteoParques(Collection $parques): array
    {
        $activos = $parques->where('is_active', true)->count();

        return ['total' => $parques->count(), 'activos' => $activos, 'inactivos' => $parques->count() - $activos];
    }

    private function plural(int $cantidad, string $singular, string $plural): string
    {
        return $cantidad.' '.($cantidad === 1 ? $singular : $plural);
    }

    private function encabezado(int $total, string $singular, string $plural): string
    {
        $mensaje = sprintf('Encontré %d %s.', $total, $total === 1 ? $singular : $plural);

        return $total > $this->maximo() ? $mensaje.' Muestro los primeros '.$this->maximo().'.' : $mensaje;
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function limite(array $p): int
    {
        return min(50, max(1, (int) ($p['limite'] ?? $this->maximo())));
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function orden(array $p, string $columna): string
    {
        return in_array($p['orden'] ?? null, ['asc', 'desc'], true)
            ? $p['orden']
            : 'asc';
    }

    private function hoy(): Carbon
    {
        return Carbon::now(config('asistente.timezone'))->startOfDay();
    }

    private function maximo(): int
    {
        return (int) config('asistente.max_items');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function respuesta(string $estado, string $intencion, string $mensaje, array $extra = []): array
    {
        return ['estado' => $estado, 'intencion' => $intencion, 'mensaje' => $mensaje, ...$extra];
    }
}
