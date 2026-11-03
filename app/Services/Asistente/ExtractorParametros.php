<?php

namespace App\Services\Asistente;

use App\Models\GreenAreaType;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Extrae los parámetros de un mensaje normalizado: módulo, período, fecha,
 * estado, formato y entidades del catálogo real (parque y trabajador).
 *
 * Los períodos relativos se calculan en el servidor, en la zona horaria del
 * asistente. La semana va del lunes al domingo (el siguiente lunes a las
 * 00:00:00 es el límite exclusivo) y los meses siguen el mismo criterio.
 */
class ExtractorParametros
{
    private const INTENCIONES_CON_PARQUE = [
        'buscar_parque', 'listar_parques', 'consultar_mantenimientos', 'consultar_mantenimientos_pendientes',
        'consultar_actividades', 'generar_reporte', 'consultar_parques', 'contar_parques',
        'consultar_puntos_agua', 'resumir_mantenimientos', 'consultar_programacion',
        'consultar_justificaciones', 'consultar_alertas',
    ];

    private const INTENCIONES_CON_TRABAJADOR = [
        'consultar_mantenimientos', 'consultar_mantenimientos_pendientes', 'consultar_actividades',
        'consultar_trabajadores', 'contar_trabajadores', 'generar_reporte', 'resumir_mantenimientos',
        'consultar_justificaciones', 'consultar_alertas',
    ];

    private const INTENCIONES_CON_MANTENIMIENTO = [
        'consultar_mantenimientos', 'consultar_mantenimientos_pendientes', 'consultar_actividades',
        'resumir_mantenimientos', 'consultar_detalle_mantenimiento', 'consultar_justificaciones',
        'generar_reporte',
    ];

    public function __construct(private readonly NormalizadorTexto $normalizador) {}

    /**
     * @return array<string, mixed>
     */
    public function extraer(string $texto, ?string $intencion = null, ?CarbonInterface $ahora = null): array
    {
        $parametros = [];

        $modulo = $this->modulo($texto, $intencion);

        if ($modulo !== null) {
            $parametros['modulo'] = $modulo;
        }

        $periodo = $this->periodo($texto, $ahora);

        if (($comparacion = $this->comparacion($texto, $ahora)) !== null) {
            $parametros['comparar'] = $comparacion;
        } else {
            $parametros = [...$parametros, ...$periodo];
        }

        $estado = $this->primero($texto, Diccionario::ESTADOS);

        if ($estado !== null) {
            $parametros['estado'] = $estado;
        }

        // "Próxima semana" es un período, no el estado "próximo".
        if (($parametros['estado'] ?? null) === 'proximo'
            && in_array($parametros['periodo'] ?? null, ['manana', 'pasado_manana', 'proxima_semana', 'proximo_mes', 'proximo_anio'], true)) {
            unset($parametros['estado']);
        }

        $formato = $this->primero($texto, Diccionario::FORMATOS);

        if ($formato !== null) {
            $parametros['formato'] = $formato;
        }

        if (in_array($intencion, self::INTENCIONES_CON_PARQUE, true)) {
            $multiples = $this->parquesMultiples($texto);
            $conector = preg_match('/\by\b|\be\b|,|\btambien\b/u', $texto) === 1;
            $entidadParque = count($multiples) > 1 && $conector
                ? ['parques' => $multiples]
                : $this->entidad('parque', $texto, $this->catalogoParques());

            $parametros = [...$parametros, ...$entidadParque];
            $parametros = [...$parametros, ...$this->filtrosParque($texto, $intencion)];
        }

        if (in_array($intencion, self::INTENCIONES_CON_TRABAJADOR, true)) {
            $parametros = [...$parametros, ...$this->entidad('trabajador', $texto, $this->catalogoTrabajadores())];
            $parametros = [...$parametros, ...$this->filtrosTrabajador($texto)];
        }

        if (in_array($intencion, self::INTENCIONES_CON_MANTENIMIENTO, true)) {
            $parametros = [...$parametros, ...$this->filtrosMantenimiento($texto)];
        }

        if ($intencion === 'generar_reporte') {
            $parametros = [...$parametros, ...$this->filtrosReporte($texto, $parametros)];
        }

        if (($quitar = $this->quitarFiltro($texto)) !== null) {
            $parametros['quitar'] = $quitar;
        }

        $termino = $this->termino($texto);

        if ($termino !== '') {
            $parametros['termino'] = $termino;
        }

        return $parametros;
    }

    /**
     * Rango de fechas (inclusivo) de un período relativo, en la zona horaria del asistente.
     *
     * @return array{desde: string, hasta: string}
     */
    public function calcularRango(string $periodo, ?CarbonInterface $ahora = null): array
    {
        $hoy = $this->hoy($ahora);

        [$desde, $hasta] = match ($periodo) {
            'hoy' => [$hoy, $hoy],
            'ayer' => [$hoy->copy()->subDay(), $hoy->copy()->subDay()],
            'anteayer' => [$hoy->copy()->subDays(2), $hoy->copy()->subDays(2)],
            'manana' => [$hoy->copy()->addDay(), $hoy->copy()->addDay()],
            'pasado_manana' => [$hoy->copy()->addDays(2), $hoy->copy()->addDays(2)],
            'semana_actual' => [$hoy->copy()->startOfWeek(Carbon::MONDAY), $hoy->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)],
            'semana_pasada' => [
                $hoy->copy()->startOfWeek(Carbon::MONDAY)->subWeek(),
                $hoy->copy()->startOfWeek(Carbon::MONDAY)->subDay(),
            ],
            'proxima_semana' => [
                $hoy->copy()->startOfWeek(Carbon::MONDAY)->addWeek(),
                $hoy->copy()->startOfWeek(Carbon::MONDAY)->addWeek()->addDays(6),
            ],
            'mes_actual' => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()->startOfDay()],
            'mes_pasado' => [
                $hoy->copy()->subMonthNoOverflow()->startOfMonth(),
                $hoy->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            'proximo_mes' => [
                $hoy->copy()->addMonthNoOverflow()->startOfMonth(),
                $hoy->copy()->addMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            'anio_actual' => [$hoy->copy()->startOfYear(), $hoy],
            'anio_pasado' => [
                $hoy->copy()->subYearNoOverflow()->startOfYear(),
                $hoy->copy()->subYearNoOverflow()->endOfYear()->startOfDay(),
            ],
            'proximo_anio' => [
                $hoy->copy()->addYearNoOverflow()->startOfYear(),
                $hoy->copy()->addYearNoOverflow()->endOfYear()->startOfDay(),
            ],
            'quincena_actual' => $hoy->day <= 15
                ? [$hoy->copy()->startOfMonth(), $hoy->copy()->startOfMonth()->addDays(14)]
                : [$hoy->copy()->startOfMonth()->addDays(15), $hoy->copy()->endOfMonth()->startOfDay()],
            'trimestre_actual' => [
                $hoy->copy()->startOfQuarter(),
                $hoy->copy()->endOfQuarter()->startOfDay(),
            ],
            'semestre_actual' => $hoy->month <= 6
                ? [$hoy->copy()->month(1)->startOfMonth(), $hoy->copy()->month(6)->endOfMonth()->startOfDay()]
                : [$hoy->copy()->month(7)->startOfMonth(), $hoy->copy()->month(12)->endOfMonth()->startOfDay()],
            'todo' => [$hoy->copy()->startOfYear(), $hoy],
        };

        return ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];
    }

    /**
     * Comparación de dos meses completos ("octubre y septiembre").
     *
     * @return list<array{desde: string, hasta: string, etiqueta: string}>|null
     */
    private function comparacion(string $texto, ?CarbonInterface $ahora): ?array
    {
        if (preg_match('/\b(?:comparar|compara|comparacion|versus|vs)\b/u', $texto) !== 1) {
            return null;
        }

        $hoy = $this->hoy($ahora);
        $meses = implode('|', array_keys(Diccionario::MESES));
        $encontrados = [];

        if (preg_match_all('/\b('.$meses.')(?:\s+(?:de|del)\s+(\d{4}))?\b/u', $texto, $coincidencias, PREG_SET_ORDER) < 2) {
            return null;
        }

        foreach (array_slice($coincidencias, 0, 2) as $coincidencia) {
            $numero = Diccionario::MESES[$coincidencia[1]];
            $anio = isset($coincidencia[2]) && $coincidencia[2] !== '' ? (int) $coincidencia[2] : $hoy->year;
            $inicio = Carbon::create($anio, $numero, 1);
            $encontrados[] = [
                'desde' => $inicio->toDateString(),
                'hasta' => $inicio->copy()->endOfMonth()->toDateString(),
                'etiqueta' => ucfirst($coincidencia[1]).' '.$anio,
            ];
        }

        return $encontrados;
    }

    private function hoy(?CarbonInterface $ahora = null): Carbon
    {
        return Carbon::instance($ahora ?? Carbon::now())->setTimezone(config('asistente.timezone'))->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function periodo(string $texto, ?CarbonInterface $ahora): array
    {
        $fechas = $this->fechasExplicitas($texto, $ahora);

        if ($fechas['invalida']) {
            return ['fecha_invalida' => true];
        }

        if ($fechas['rango_invalido']) {
            return ['rango_invalido' => true];
        }

        if ($fechas['rango'] !== null) {
            return ['periodo' => 'rango', ...$fechas['rango']];
        }

        if ($fechas['validas'] !== []) {
            sort($fechas['validas']);

            return [
                'periodo' => count($fechas['validas']) === 1 ? 'fecha' : 'rango',
                'desde' => $fechas['validas'][0],
                'hasta' => $fechas['validas'][array_key_last($fechas['validas'])],
            ];
        }

        if ($fechas['mes'] !== null) {
            return ['periodo' => 'mes_nombre', ...$fechas['mes']];
        }

        $especial = $this->periodoEspecial($texto, $ahora);

        if ($especial !== null) {
            return $especial;
        }

        $hoy = $this->hoy($ahora);
        $desdeMarcador = $this->marcadorDesde($texto);
        $hastaMarcador = $this->marcadorHasta($texto);
        $hastaHoy = $this->marcadorHastaHoy($texto);

        foreach (Diccionario::PERIODOS as $periodo => $frases) {
            if (! Diccionario::coincide($texto, $frases)) {
                continue;
            }

            $rango = $this->calcularRango($periodo, $ahora);

            if ($hastaHoy && $periodo !== 'hoy') {
                return $this->respuestaRango($rango['desde'], $hoy->toDateString());
            }

            if ($desdeMarcador && ! $hastaMarcador) {
                return $this->respuestaRango($rango['desde'], $hoy->toDateString());
            }

            if ($hastaMarcador && ! $desdeMarcador) {
                return $this->respuestaRango($hoy->copy()->startOfYear()->toDateString(), $rango['hasta']);
            }

            return ['periodo' => $periodo, ...$rango];
        }

        return [];
    }

    /**
     * Períodos que no son una simple palabra: desplazamientos ("hace 3 días"),
     * ventanas móviles ("últimos 30 días"), días de la semana y años sueltos.
     *
     * @return ?array<string, mixed>
     */
    private function periodoEspecial(string $texto, ?CarbonInterface $ahora): ?array
    {
        $hoy = $this->hoy($ahora);

        if (preg_match('/\b(?:los\s+)?ultimos\s+(\d{1,3}|[a-z]+)\s+(dias?|semanas?|meses?)\b/u', $texto, $m) === 1) {
            $cantidad = $this->numero($m[1]);

            if ($cantidad !== null && $cantidad > 0) {
                $inicio = match (true) {
                    str_starts_with($m[2], 'semana') => $hoy->copy()->subWeeks($cantidad)->addDay(),
                    str_starts_with($m[2], 'mes') => $hoy->copy()->subMonthsNoOverflow($cantidad)->addDay(),
                    default => $hoy->copy()->subDays($cantidad)->addDay(),
                };

                return $this->respuestaRango($inicio->toDateString(), $hoy->toDateString());
            }
        }

        if (preg_match('/\b(?:los\s+)?proximos\s+(\d{1,3}|[a-z]+)\s+(dias?|semanas?|meses?)\b/u', $texto, $m) === 1) {
            $cantidad = $this->numero($m[1]);

            if ($cantidad !== null && $cantidad > 0) {
                $fin = match (true) {
                    str_starts_with($m[2], 'semana') => $hoy->copy()->addWeeks($cantidad)->subDay(),
                    str_starts_with($m[2], 'mes') => $hoy->copy()->addMonthsNoOverflow($cantidad)->subDay(),
                    default => $hoy->copy()->addDays($cantidad)->subDay(),
                };

                return $this->respuestaRango($hoy->toDateString(), $fin->toDateString());
            }
        }

        if (preg_match('/\bhace\s+(\d{1,3}|[a-z]+)\s+(dias?|semanas?|meses?)\b/u', $texto, $m) === 1) {
            $cantidad = $this->numero($m[1]);

            if ($cantidad !== null && $cantidad > 0) {
                $fecha = match (true) {
                    str_starts_with($m[2], 'semana') => $hoy->copy()->subWeeks($cantidad),
                    str_starts_with($m[2], 'mes') => $hoy->copy()->subMonthsNoOverflow($cantidad),
                    default => $hoy->copy()->subDays($cantidad),
                };

                return ['periodo' => 'fecha', 'desde' => $fecha->toDateString(), 'hasta' => $fecha->toDateString()];
            }
        }

        if (preg_match('/\b(?:dentro de|en)\s+(\d{1,3}|[a-z]+)\s+(dias?|semanas?)\b/u', $texto, $m) === 1) {
            $cantidad = $this->numero($m[1]);

            if ($cantidad !== null && $cantidad > 0) {
                $fecha = str_starts_with($m[2], 'semana')
                    ? $hoy->copy()->addWeeks($cantidad)
                    : $hoy->copy()->addDays($cantidad);

                return ['periodo' => 'fecha', 'desde' => $fecha->toDateString(), 'hasta' => $fecha->toDateString()];
            }
        }

        $dias = implode('|', array_keys(Diccionario::DIAS_SEMANA));

        if (preg_match('/\b(?:(este|esta|el|la|del|proximo|proxima|pasado|pasada)\s+)?('.$dias.')(?:\s+(pasado|pasada|proximo|proxima|que viene))?\b/u', $texto, $m) === 1) {
            $objetivo = Diccionario::DIAS_SEMANA[$m[2]];
            $modificador = $m[1].' '.($m[3] ?? '');
            $hoyDia = $hoy->dayOfWeekIso;

            if (preg_match('/proximo|proxima|que viene/u', $modificador) === 1) {
                $diasDiferencia = ($objetivo - $hoyDia + 7) % 7 ?: 7;
                $fecha = $hoy->copy()->addDays($diasDiferencia);
            } elseif (preg_match('/pasado|pasada/u', $modificador) === 1) {
                $diasDiferencia = ($hoyDia - $objetivo + 7) % 7 ?: 7;
                $fecha = $hoy->copy()->subDays($diasDiferencia);
            } else {
                $fecha = $hoy->copy()->subDays(($hoyDia - $objetivo + 7) % 7);
            }

            return ['periodo' => 'fecha', 'desde' => $fecha->toDateString(), 'hasta' => $fecha->toDateString()];
        }

        if (preg_match('/\b(?:para|en|del?|durante)\s+(?:el\s+)?(?:ano\s+)?(20\d{2})\b/u', $texto, $m) === 1) {
            $anio = (int) $m[1];
            $inicio = Carbon::create($anio, 1, 1)->toDateString();
            $fin = $anio === $hoy->year
                ? $hoy->toDateString()
                : Carbon::create($anio, 12, 31)->toDateString();

            return $this->respuestaRango($inicio, $fin);
        }

        return null;
    }

    private function numero(string $valor): ?int
    {
        if (preg_match('/^\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        return Diccionario::NUMEROS_PALABRA[$valor] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function respuestaRango(string $desde, string $hasta): array
    {
        return $desde <= $hasta
            ? ['periodo' => 'rango', 'desde' => $desde, 'hasta' => $hasta]
            : ['rango_invalido' => true];
    }

    /**
     * @return array{validas: list<string>, invalida: bool, rango: ?array{desde: string, hasta: string}, rango_invalido: bool, mes: ?array{desde: string, hasta: string}}
     */
    private function fechasExplicitas(string $texto, ?CarbonInterface $ahora): array
    {
        $hoy = $this->hoy($ahora);
        $anioActual = $hoy->year;
        $mesActual = $hoy->month;
        $validas = [];
        $invalida = false;
        $rango = null;
        $rangoInvalido = false;
        $mesCompleto = null;
        $restante = $texto;

        // 1. Fechas numéricas completas: 2026-10-14, 14/10/2026 o 14-10-2026.
        $restante = preg_replace_callback('/\b(\d{1,4})[\/-](\d{1,2})[\/-](\d{1,4})\b/u', function (array $m) use (&$validas, &$invalida) {
            [$dia, $mes, $anio] = match (true) {
                strlen($m[1]) === 4 => [(int) $m[3], (int) $m[2], (int) $m[1]],
                strlen($m[3]) === 4 => [(int) $m[1], (int) $m[2], (int) $m[3]],
                default => [0, 0, 0],
            };

            if (checkdate($mes, $dia, $anio)) {
                $validas[] = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            } else {
                $invalida = true;
            }

            return ' ';
        }, $restante) ?? $restante;

        // 2. Fechas numéricas sin año: 14/10.
        $restante = preg_replace_callback('/\b(\d{1,2})\/(\d{1,2})\b/u', function (array $m) use (&$validas, &$invalida, $anioActual) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];

            if (checkdate($mes, $dia, $anioActual)) {
                $validas[] = sprintf('%04d-%02d-%02d', $anioActual, $mes, $dia);
            } else {
                $invalida = true;
            }

            return ' ';
        }, $restante) ?? $restante;

        $meses = $this->mesesPatron();
        $dia = '(\d{1,2}|primero|uno|1ro|1er)';
        $anio = '(\d{4})';
        $mes = "({$meses})";

        // 3. Rangos con día: "del 5 al 20 de octubre", "entre el 5 y el 20 de octubre".
        $patronDias = "/\b(?:desde|de|del|entre)\s+(?:el\s+)?(?:dia\s+)?{$dia}(?:\s+(?:de\s+)?{$mes})?(?:\s+(?:de|del)\s+{$anio})?\s+(?:hasta|al|a|y)\s+(?:el\s+)?(?:dia\s+)?{$dia}(?:\s+(?:de\s+)?{$mes})?(?:\s+(?:de|del)\s+{$anio})?\b/u";

        $restante = preg_replace_callback($patronDias, function (array $m) use (&$rango, &$rangoInvalido, &$invalida, $anioActual, $mesActual) {
            $armado = $this->resolverDias(
                $this->numeroDia($m[1]),
                ($m[2] ?? '') !== '' ? Diccionario::MESES[$m[2]] : null,
                ($m[3] ?? '') !== '' ? (int) $m[3] : null,
                $this->numeroDia($m[4]),
                ($m[5] ?? '') !== '' ? Diccionario::MESES[$m[5]] : null,
                ($m[6] ?? '') !== '' ? (int) $m[6] : null,
                $anioActual,
                $mesActual,
            );

            $this->registrarRango($armado, $rango, $rangoInvalido, $invalida);

            return ' ';
        }, $restante) ?? $restante;

        // 4. Del mes al día: "de septiembre al 20 de octubre".
        $patronMesDia = "/\b(?:desde|de|del|entre)\s+(?:el\s+)?(?:mes\s+de\s+)?{$mes}(?:\s+(?:de|del)\s+{$anio})?\s+(?:hasta|al|a|y)\s+(?:el\s+)?(?:dia\s+)?{$dia}(?:\s+(?:de\s+)?{$mes})?(?:\s+(?:de|del)\s+{$anio})?\b/u";

        $restante = preg_replace_callback($patronMesDia, function (array $m) use (&$rango, &$rangoInvalido, &$invalida, $anioActual) {
            $m1 = Diccionario::MESES[$m[1]];
            $m2 = ($m[4] ?? '') !== '' ? Diccionario::MESES[$m[4]] : $m1;

            $armado = $this->resolverMesDia(
                $m1,
                ($m[2] ?? '') !== '' ? (int) $m[2] : null,
                $this->numeroDia($m[3]),
                $m2,
                ($m[5] ?? '') !== '' ? (int) $m[5] : null,
                $anioActual,
            );

            $this->registrarRango($armado, $rango, $rangoInvalido, $invalida);

            return ' ';
        }, $restante) ?? $restante;

        // 5. Rangos de meses completos: "de septiembre a noviembre".
        $patronMeses = "/\b(?:(?:desde|de|del|entre)\s+)?(?:el\s+)?(?:mes\s+de\s+)?{$mes}(?:\s+(?:de|del)\s+{$anio})?\s+(?:hasta|al|a|y)\s+(?:el\s+)?(?:mes\s+de\s+)?{$mes}(?:\s+(?:de|del)\s+{$anio})?\b/u";

        $restante = preg_replace_callback($patronMeses, function (array $m) use (&$rango, &$rangoInvalido, &$invalida, $anioActual) {
            $armado = $this->resolverMeses(
                Diccionario::MESES[$m[1]],
                ($m[2] ?? '') !== '' ? (int) $m[2] : null,
                Diccionario::MESES[$m[3]],
                ($m[4] ?? '') !== '' ? (int) $m[4] : null,
                $anioActual,
            );

            $this->registrarRango($armado, $rango, $rangoInvalido, $invalida);

            return ' ';
        }, $restante) ?? $restante;

        // 6. Día con mes: "14 de octubre", "el día 5 de setiembre", "5 octubre".
        $patronDiaMes = "/\b(?:(?:el|del)\s+)?(?:dia\s+)?{$dia}\s+(?:de\s+)?{$mes}(?:\s+(?:de|del)\s+{$anio})?\b/u";

        $restante = preg_replace_callback($patronDiaMes, function (array $m) use (&$validas, &$invalida, $anioActual) {
            $dia = $this->numeroDia($m[1]);
            $mes = Diccionario::MESES[$m[2]];
            $anio = ($m[3] ?? '') !== '' ? (int) $m[3] : $anioActual;

            if (checkdate($mes, $dia, $anio)) {
                $validas[] = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            } else {
                $invalida = true;
            }

            return ' ';
        }, $restante) ?? $restante;

        // 7. Día suelto: "del día 14". Usa el mes del período relativo o el actual.
        $referencia = $this->mesDeReferencia($texto, $hoy);
        $patronDiaSolo = "/\b(?:(?:el|del)\s+)?dia\s+{$dia}\b/u";

        $restante = preg_replace_callback($patronDiaSolo, function (array $m) use (&$validas, &$invalida, $referencia) {
            $dia = $this->numeroDia($m[1]);

            if (checkdate($referencia['mes'], $dia, $referencia['anio'])) {
                $validas[] = sprintf('%04d-%02d-%02d', $referencia['anio'], $referencia['mes'], $dia);
            } else {
                $invalida = true;
            }

            return ' ';
        }, $restante) ?? $restante;

        // 8. Mes completo: "septiembre", "septiembre de 2025".
        if (preg_match("/\b{$mes}(?:\s+(?:de|del)\s+{$anio})?\b/u", $restante, $m) === 1) {
            $anioMes = ($m[2] ?? '') !== '' ? (int) $m[2] : $anioActual;
            $mesNumero = Diccionario::MESES[$m[1]];

            if (! checkdate($mesNumero, 1, $anioMes)) {
                $invalida = true;
            } else {
                $inicio = sprintf('%04d-%02d-01', $anioMes, $mesNumero);
                $fin = Carbon::create($anioMes, $mesNumero, 1)->endOfMonth()->toDateString();
                $desdeMarcador = $this->marcadorDesde($texto);
                $hastaMarcador = $this->marcadorHasta($texto);
                $hastaHoy = $this->marcadorHastaHoy($texto);

                $candidato = match (true) {
                    $hastaHoy => $this->rangoValido($inicio, $hoy->toDateString()),
                    $desdeMarcador && ! $hastaMarcador => $this->rangoValido($inicio, $hoy->toDateString()),
                    $hastaMarcador && ! $desdeMarcador => $this->rangoValido($hoy->copy()->startOfYear()->toDateString(), $fin),
                    default => null,
                };

                if ($candidato !== null) {
                    $rango = $candidato;
                } elseif (! $hastaHoy && ! $desdeMarcador && ! $hastaMarcador) {
                    $mesCompleto = ['desde' => $inicio, 'hasta' => $fin];
                } else {
                    $rangoInvalido = true;
                }
            }
        }

        // Un rango explícito con las fechas invertidas es un error, no un rango ordenado.
        if (! $invalida && ! $rangoInvalido && $rango === null && count($validas) === 2
            && preg_match('/\b(?:hasta|al|entre)\b/u', $texto) === 1
            && $validas[0] > $validas[1]) {
            $rangoInvalido = true;
        }

        // "Desde el 5 de octubre" va hasta hoy; "hasta el 5" empieza el 1 de enero.
        if (! $invalida && ! $rangoInvalido && $rango === null && count($validas) === 1) {
            $fecha = $validas[0];
            $desdeMarcador = $this->marcadorDesde($texto);
            $hastaMarcador = $this->marcadorHasta($texto);
            $hastaHoy = $this->marcadorHastaHoy($texto);

            $candidato = match (true) {
                $hastaHoy => $this->rangoValido($fecha, $hoy->toDateString()),
                $desdeMarcador && ! $hastaMarcador => $this->rangoValido($fecha, $hoy->toDateString()),
                $hastaMarcador && ! $desdeMarcador => $this->rangoValido($hoy->copy()->startOfYear()->toDateString(), $fecha),
                default => null,
            };

            if ($candidato !== null) {
                $rango = $candidato;
            } elseif ($hastaHoy || ($desdeMarcador xor $hastaMarcador)) {
                $rangoInvalido = true;
            }
        }

        return [
            'validas' => $validas,
            'invalida' => $invalida,
            'rango' => $rango,
            'rango_invalido' => $rangoInvalido,
            'mes' => $mesCompleto,
        ];
    }

    /**
     * @param  array{desde: string, hasta: string}|array{invalido: true, invertido: bool}  $armado
     * @param  ?array{desde: string, hasta: string}  $rango
     */
    private function registrarRango(array $armado, ?array &$rango, bool &$rangoInvalido, bool &$invalida): void
    {
        if (isset($armado['invalido'])) {
            if ($armado['invertido']) {
                $rangoInvalido = true;
            } else {
                $invalida = true;
            }

            return;
        }

        $rango ??= $armado;
    }

    /**
     * @return array{desde: string, hasta: string}|array{invalido: true, invertido: bool}
     */
    private function resolverDias(int $d1, ?int $m1, ?int $y1, int $d2, ?int $m2, ?int $y2, int $anioActual, int $mesActual): array
    {
        $m1Explicito = $m1 !== null;
        $m2Explicito = $m2 !== null;

        if (! $m1Explicito && ! $m2Explicito) {
            $m1 = $m2 = $mesActual;
        } elseif (! $m1Explicito) {
            $m1 = $m2;

            if ($d1 > $d2) {
                $m1 = $m2 === 1 ? 12 : $m2 - 1;
            }
        } elseif (! $m2Explicito) {
            $m2 = $m1;

            if ($d2 < $d1) {
                $m2 = $m1 === 12 ? 1 : $m1 + 1;
            }
        }

        if ($y1 === null && $y2 === null) {
            if (! $m1Explicito && $m2Explicito) {
                $y2 = $anioActual;
                $y1 = $y2 - ($m1 > $m2 ? 1 : 0);
            } else {
                $y1 = $anioActual;
                $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
            }
        } elseif ($y1 === null) {
            $y1 = $y2 - ($m1 > $m2 ? 1 : 0);
        } elseif ($y2 === null) {
            $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
        }

        return $this->armarRango($y1, $m1, $d1, $y2, $m2, $d2);
    }

    /**
     * @return array{desde: string, hasta: string}|array{invalido: true, invertido: bool}
     */
    private function resolverMesDia(int $m1, ?int $y1, int $d2, int $m2, ?int $y2, int $anioActual): array
    {
        if ($y1 === null && $y2 === null) {
            $y1 = $anioActual;
            $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
        } elseif ($y1 === null) {
            $y1 = $y2 - ($m1 > $m2 ? 1 : 0);
        } elseif ($y2 === null) {
            $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
        }

        return $this->armarRango($y1, $m1, 1, $y2, $m2, $d2);
    }

    /**
     * @return array{desde: string, hasta: string}|array{invalido: true, invertido: bool}
     */
    private function resolverMeses(int $m1, ?int $y1, int $m2, ?int $y2, int $anioActual): array
    {
        if ($y1 === null && $y2 === null) {
            $y1 = $anioActual;
            $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
        } elseif ($y1 === null) {
            $y1 = $y2 - ($m1 > $m2 ? 1 : 0);
        } elseif ($y2 === null) {
            $y2 = $y1 + ($m2 < $m1 ? 1 : 0);
        }

        if (! checkdate($m1, 1, $y1) || ! checkdate($m2, 1, $y2)) {
            return ['invalido' => true, 'invertido' => false];
        }

        $inicio = sprintf('%04d-%02d-01', $y1, $m1);
        $fin = Carbon::create($y2, $m2, 1)->endOfMonth()->toDateString();

        return $inicio <= $fin
            ? ['desde' => $inicio, 'hasta' => $fin]
            : ['invalido' => true, 'invertido' => true];
    }

    /**
     * @return array{desde: string, hasta: string}|array{invalido: true, invertido: bool}
     */
    private function armarRango(int $y1, int $m1, int $d1, int $y2, int $m2, int $d2): array
    {
        if (! checkdate($m1, $d1, $y1) || ! checkdate($m2, $d2, $y2)) {
            return ['invalido' => true, 'invertido' => false];
        }

        $inicio = sprintf('%04d-%02d-%02d', $y1, $m1, $d1);
        $fin = sprintf('%04d-%02d-%02d', $y2, $m2, $d2);

        return $inicio <= $fin
            ? ['desde' => $inicio, 'hasta' => $fin]
            : ['invalido' => true, 'invertido' => true];
    }

    private function numeroDia(string $valor): int
    {
        return in_array($valor, ['primero', 'uno', '1ro', '1er'], true) ? 1 : (int) $valor;
    }

    private function mesesPatron(): string
    {
        return implode('|', array_keys(Diccionario::MESES));
    }

    private function marcadorDesde(string $texto): bool
    {
        return preg_match('/\bdesde\b/u', $texto) === 1;
    }

    private function marcadorHasta(string $texto): bool
    {
        return preg_match('/\bhasta\b/u', $texto) === 1;
    }

    private function marcadorHastaHoy(string $texto): bool
    {
        return preg_match('/\b(?:hasta|al|a)\s+(?:el\s+)?(?:dia\s+)?(?:de\s+)?(?:hoy|la fecha)\b/u', $texto) === 1;
    }

    /**
     * @return ?array{desde: string, hasta: string}
     */
    private function rangoValido(string $desde, string $hasta): ?array
    {
        return $desde <= $hasta ? ['desde' => $desde, 'hasta' => $hasta] : null;
    }

    /**
     * @return array{anio: int, mes: int}
     */
    private function mesDeReferencia(string $texto, Carbon $hoy): array
    {
        if (Diccionario::coincide($texto, Diccionario::PERIODOS['mes_pasado'])) {
            $fecha = $hoy->copy()->subMonthNoOverflow();

            return ['anio' => $fecha->year, 'mes' => $fecha->month];
        }

        if (Diccionario::coincide($texto, Diccionario::PERIODOS['anio_pasado'])) {
            return ['anio' => $hoy->year - 1, 'mes' => $hoy->month];
        }

        return ['anio' => $hoy->year, 'mes' => $hoy->month];
    }

    /**
     * Clave del diccionario cuyas frases aparecen antes en el texto.
     *
     * @param  array<string, list<string>>  $grupos
     */
    private function primero(string $texto, array $grupos): ?string
    {
        $mejor = null;
        $posicionMejor = PHP_INT_MAX;

        foreach ($grupos as $clave => $frases) {
            $posicion = Diccionario::posicion($texto, $frases);

            if ($posicion !== null && $posicion < $posicionMejor) {
                $mejor = $clave;
                $posicionMejor = $posicion;
            }
        }

        return $mejor;
    }

    private function modulo(string $texto, ?string $intencion): ?string
    {
        $modulos = Diccionario::modulosEn($texto);

        if ($modulos === []) {
            return $this->moduloPorEstado($texto, $intencion);
        }

        if ($intencion === 'navegar_modulo') {
            asort($modulos);

            return array_key_first($modulos);
        }

        unset($modulos['reportes']);

        if ($modulos === []) {
            return $this->moduloPorEstado($texto, $intencion);
        }

        foreach (['mantenimientos', 'trabajadores', 'parques', 'dashboard', 'configuracion'] as $modulo) {
            if (isset($modulos[$modulo])) {
                return $modulo;
            }
        }

        return null;
    }

    private function moduloPorEstado(string $texto, ?string $intencion): ?string
    {
        // "Reporte de los vencidos": el estado ya implica mantenimientos.
        return $intencion === 'generar_reporte' && $this->primero($texto, Diccionario::ESTADOS) !== null
            ? 'mantenimientos'
            : null;
    }

    /**
     * Palabras que no son del vocabulario ni parte de una fecha: posible nombre propio.
     */
    private function termino(string $texto): string
    {
        $vocabulario = Diccionario::vocabulario();

        $palabras = array_filter(
            $this->normalizador->palabras($texto),
            fn (string $palabra) => strlen($palabra) >= 3 && ! isset($vocabulario[$palabra]) && ! preg_match('/\d/', $palabra),
        );

        return implode(' ', $palabras);
    }

    /**
     * @param  list<array{id: int, nombre: string, codigo: ?string, etiqueta: string}>  $catalogo
     * @return array<string, mixed>
     */
    private function entidad(string $clave, string $texto, array $catalogo): array
    {
        if ($catalogo === []) {
            return [];
        }

        // 1. Código exacto (PRQ-0005, TRB-0001).
        foreach ($catalogo as $item) {
            if ($item['codigo'] !== null && preg_match('/\b'.preg_quote($item['codigo'], '/').'\b/', $texto) === 1) {
                return [$clave => ['id' => $item['id'], 'nombre' => $item['etiqueta']]];
            }
        }

        // 2. Nombre completo como frase; gana el más largo.
        $coincidencias = array_filter($catalogo, fn (array $item) => $item['nombre'] !== ''
            && preg_match('/\b'.preg_quote($item['nombre'], '/').'\b/', $texto) === 1);

        if ($coincidencias !== []) {
            $mayor = max(array_map(fn (array $item) => strlen($item['nombre']), $coincidencias));
            $mejores = array_values(array_filter($coincidencias, fn (array $item) => strlen($item['nombre']) === $mayor));

            return $this->resultadoEntidad($clave, $mejores);
        }

        // 3. Palabras del texto que aparecen en nombres del catálogo.
        $vocabulario = Diccionario::vocabulario();
        $palabras = array_values(array_unique(array_filter(
            $this->normalizador->palabras($texto),
            fn (string $palabra) => strlen($palabra) >= 3 && ! isset($vocabulario[$palabra]),
        )));

        $coinciden = array_values(array_filter($palabras, function (string $palabra) use ($catalogo, $clave) {
            if ($clave === 'parque' && isset($this->palabrasZona()[$palabra])) {
                return false;
            }

            foreach ($catalogo as $item) {
                if (in_array($palabra, explode(' ', $item['nombre']), true)) {
                    return true;
                }
            }

            return false;
        }));

        if ($coinciden === []) {
            return [];
        }

        $todas = array_values(array_filter($catalogo, fn (array $item) => array_diff($coinciden, explode(' ', $item['nombre'])) === []));

        if ($todas === []) {
            $todas = array_values(array_filter($catalogo, fn (array $item) => array_intersect($coinciden, explode(' ', $item['nombre'])) !== []));
        }

        return $this->resultadoEntidad($clave, $todas);
    }

    /**
     * Varios parques mencionados a la vez ("Comet y Villa Maria").
     *
     * @return list<array{id: int, nombre: string}>
     */
    private function parquesMultiples(string $texto): array
    {
        $encontrados = [];

        foreach ($this->catalogoParques() as $parque) {
            if ($parque['nombre'] !== '' && preg_match('/\b'.preg_quote($parque['nombre'], '/').'\b/u', $texto) === 1) {
                $encontrados[$parque['id']] = ['id' => $parque['id'], 'nombre' => $parque['etiqueta']];
            }
        }

        return array_values($encontrados);
    }

    /**
     * @param  list<array{id: int, nombre: string, codigo: ?string, etiqueta: string}>  $items
     * @return array<string, mixed>
     */
    private function resultadoEntidad(string $clave, array $items): array
    {
        if (count($items) === 1) {
            return [$clave => ['id' => $items[0]['id'], 'nombre' => $items[0]['etiqueta']]];
        }

        return [$clave.'_candidatos' => array_map(
            fn (array $item) => ['id' => $item['id'], 'nombre' => $item['etiqueta']],
            array_slice($items, 0, 5),
        )];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosParque(string $texto, string $intencion): array
    {
        $filtros = [];

        $plural = preg_match('/\bparques?\b|\bareas? verdes?\b|\bplazas?\b|\bovalo?s?\b|\bjardines?\b|\bbermas?\b|\balamedas?\b/u', $texto) === 1;

        if (preg_match('/\b(?:sin|no tienen|no tiene|falta de|carecen de)\s+(?:punto de agua|agua)\b/u', $texto) === 1) {
            $filtros['agua'] = 'sin';
        } elseif ($plural && preg_match('/\b(?:con|tienen|tiene)\s+(?:punto de agua|agua)\b/u', $texto) === 1) {
            $filtros['agua'] = 'con';
        }

        if (($estadoAgua = $this->valorCatalogo($texto, $this->valores('water_point_status'))) !== null) {
            $filtros['agua_estado'] = $estadoAgua;
        }

        if (($abastecimiento = $this->valorCatalogo($texto, $this->valores('water_supply_type'))) !== null) {
            $filtros['agua_abastecimiento'] = $abastecimiento;
        }

        if (($tipo = $this->valorCatalogo($texto, $this->tiposArea())) !== null) {
            // "parques" es el nombre genérico del módulo, no un filtro por tipo.
            if (! in_array(mb_strtolower(trim($tipo)), ['parque', 'area verde', 'areas verdes'], true)) {
                $filtros['parque_tipo'] = $tipo;
            }
        }

        if (($zona = $this->valorCatalogo($texto, $this->valores('zone'))) !== null) {
            $filtros['parque_zona'] = $zona;
        }

        if (($conservacion = $this->primero($texto, Diccionario::CONSERVACION)) !== null) {
            $filtros['parque_conservacion'] = $conservacion;
        }

        if (preg_match('/\binactivos?\b|\binactivas?\b/u', $texto) === 1) {
            $filtros['parque_activo'] = false;
        } elseif (preg_match('/\bactivos?\b|\bactivas?\b/u', $texto) === 1) {
            $filtros['parque_activo'] = true;
        }

        if (preg_match('/\bentre\s+([\d.,]+)\s+y\s+([\d.,]+)\s*(?:m2|m|metros?)\b/u', $texto, $m) === 1) {
            $filtros['superficie_min'] = $this->numeroDecimal($m[1]);
            $filtros['superficie_max'] = $this->numeroDecimal($m[2]);
        } elseif (preg_match('/\b(?:mas de|mayor a|mayor de|superior a)\s+([\d.,]+)\s*(?:m2|m|metros?)\b/u', $texto, $m) === 1) {
            $filtros['superficie_min'] = $this->numeroDecimal($m[1]);
        } elseif (preg_match('/\b(?:menos de|menor a|menor de|hasta)\s+([\d.,]+)\s*(?:m2|m|metros?)\b/u', $texto, $m) === 1) {
            $filtros['superficie_max'] = $this->numeroDecimal($m[1]);
        }

        foreach (['con_foto', 'sin_foto', 'con_ubicacion', 'sin_ubicacion', 'con_observaciones', 'sin_observaciones'] as $clave) {
            if (Diccionario::coincide($texto, Diccionario::EXISTENCIA[$clave])) {
                $filtros[$clave] = true;
            }
        }

        if (preg_match('/\bregistr\w*\b/u', $texto) === 1) {
            $filtros['fecha_registro'] = true;
        }

        $agrupar = $this->agruparPor($texto);

        if (in_array($agrupar, ['zona', 'tipo', 'conservacion'], true)) {
            $filtros['agrupar_por'] = $agrupar;
        }

        return [...$filtros, ...$this->ordenYLimite($texto)];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosTrabajador(string $texto): array
    {
        $filtros = [];

        if (preg_match('/\binactivos?\b|\binactivas?\b/u', $texto) === 1) {
            $filtros['trabajador_activo'] = false;
        } elseif (preg_match('/\bactivos?\b|\bactivas?\b/u', $texto) === 1) {
            $filtros['trabajador_activo'] = true;
        }

        if (($cargo = $this->valorCatalogo($texto, $this->valores('position', 'workers')) ?? $this->valorPorRaiz($texto, $this->valores('position', 'workers'))) !== null) {
            $filtros['trabajador_cargo'] = $cargo;
        }

        if (($cuadrilla = $this->valorCatalogo($texto, $this->valores('crew', 'workers'))) !== null) {
            $filtros['trabajador_cuadrilla'] = $cuadrilla;
        }

        if (preg_match('/\bsin\s+(?:trabajos?|mantenimientos?|asignaciones?|pendientes?)\b/u', $texto) === 1) {
            $filtros['sin_trabajos'] = true;
        }

        $agrupar = $this->agruparPor($texto);

        if (in_array($agrupar, ['cuadrilla', 'cargo'], true)) {
            $filtros['agrupar_por'] = $agrupar;
        }

        return [...$filtros, ...$this->ordenYLimite($texto)];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosMantenimiento(string $texto): array
    {
        $filtros = [];

        if (($tipo = $this->tipoMantenimiento($texto)) !== null) {
            $filtros['tipo_mantenimiento'] = $tipo;
        } elseif (($desconocido = $this->tipoDesconocido($texto)) !== null) {
            $filtros['tipo_mantenimiento_invalido'] = $desconocido;
        }

        if (($origen = $this->primero($texto, Diccionario::ORIGEN)) !== null
            && preg_match('/\b(?:mantenimiento|mantenimientos|trabajo|trabajos|orden|ordenes)\b/u', $texto) === 1) {
            $filtros['origen'] = $origen;
        }

        if (($asignacion = $this->primero($texto, Diccionario::ASIGNACION)) !== null) {
            $filtros['asignacion'] = $asignacion;
        }

        if (($duracion = $this->duracion($texto)) !== []) {
            $filtros = [...$filtros, ...$duracion];
        }

        if (preg_match('/\b(?:mas de|mayor a|mayor de)\s+(\d{1,4}|[a-z]+)\s+dias?\s+de\s+(?:atraso|retraso|vencido)\b/u', $texto, $m) === 1
            && ($dias = $this->numero($m[1])) !== null) {
            $filtros['retraso_min'] = $dias;
            $filtros['estado'] = 'vencido';
        }

        if (($cuadrilla = $this->valorCatalogo($texto, $this->valores('crew', 'workers'))) !== null) {
            $filtros['trabajador_cuadrilla'] = $cuadrilla;
        }

        if (($cargo = $this->valorCatalogo($texto, $this->valores('position', 'workers')) ?? $this->valorPorRaiz($texto, $this->valores('position', 'workers'))) !== null) {
            $filtros['trabajador_cargo'] = $cargo;
        }

        if (Diccionario::coincide($texto, Diccionario::EXISTENCIA['con_documento'])) {
            $filtros['con_documento'] = true;
        } elseif (Diccionario::coincide($texto, Diccionario::EXISTENCIA['sin_documento'])) {
            $filtros['con_documento'] = false;
        }

        if (Diccionario::coincide($texto, Diccionario::EXISTENCIA['con_foto'])) {
            $filtros['con_foto'] = true;
        } elseif (Diccionario::coincide($texto, Diccionario::EXISTENCIA['sin_foto'])) {
            $filtros['con_foto'] = false;
        }

        if (preg_match('/\bpm-(\d+)(?:-(\d{4}))?/u', $texto, $m) === 1) {
            $filtros['numero_orden'] = 'PM-'.str_pad($m[1], 6, '0', STR_PAD_LEFT).(isset($m[2]) && $m[2] !== '' ? '-'.$m[2] : '');
        } elseif (preg_match('/\b(?:mantenimiento|orden)\s+(?:numero\s+)?(\d{1,6})\b/u', $texto, $m) === 1) {
            $filtros['orden_id'] = (int) $m[1];
        }

        if (preg_match('/\b(?:historial|todos los mantenimientos|todas las ordenes|todo el historial)\b/u', $texto) === 1) {
            $filtros['historial'] = true;
        }

        if (preg_match('/\b(?:que digan|que mencionen|que contengan|con la palabra|busca en (?:las )?observaciones|en las observaciones)\b/u', $texto) === 1) {
            $limpio = trim((string) preg_replace('/^.*?\b(?:que digan|que mencionen|que contengan|con la palabra|busca en (?:las )?observaciones|en las observaciones)\b/u', '', $texto));

            if ($limpio !== '') {
                $filtros['busqueda_texto'] = $limpio;
            }
        }

        $agrupar = $this->agruparPor($texto);

        if ($agrupar !== null) {
            $filtros['agrupar_por'] = $agrupar;
        }

        return [...$filtros, ...$this->ordenYLimite($texto)];
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    private function filtrosReporte(string $texto, array $parametros): array
    {
        $filtros = [];

        $modulo = match (true) {
            Diccionario::coincide($texto, Diccionario::JUSTIFICACIONES) => 'justificaciones',
            preg_match('/\bhistorial\b/u', $texto) === 1 => 'historial',
            preg_match('/\balertas?\b/u', $texto) === 1 => 'alertas',
            Diccionario::coincide($texto, Diccionario::AGUA) => 'agua',
            default => null,
        };

        if ($modulo !== null) {
            $filtros['modulo'] = $modulo;
        }

        if (($parametros['agua'] ?? null) === 'sin' || ($parametros['agua'] ?? null) === 'con') {
            $filtros['modulo'] = 'agua';
        }

        return $filtros;
    }

    /**
     * Dimensión de agrupación pedida explícitamente.
     */
    private function agruparPor(string $texto): ?string
    {
        return match (true) {
            preg_match('/\bpor\s+(?:cada\s+)?tipo\b|\btipos distintos\b|\bde cada tipo\b|\bcuantos de cada\b/u', $texto) === 1 => 'tipo',
            preg_match('/\bpor\s+(?:cada\s+)?parque\b|\bde cada parque\b|\bcuantos por parque\b/u', $texto) === 1 => 'parque',
            preg_match('/\bpor\s+(?:cada\s+)?trabajador\b|\bde cada trabajador\b|\bcuantos por trabajador\b/u', $texto) === 1 => 'trabajador',
            preg_match('/\bpor\s+(?:cada\s+)?estado\b|\bde cada estado\b/u', $texto) === 1 => 'estado',
            preg_match('/\bpor\s+(?:cada\s+)?zona\b|\bde cada zona\b/u', $texto) === 1 => 'zona',
            preg_match('/\bpor\s+(?:cada\s+)?mes\b|\bde cada mes\b|\bpor mes\b/u', $texto) === 1 => 'mes',
            preg_match('/\bpor\s+(?:cada\s+)?cuadrilla\b|\bde cada cuadrilla\b/u', $texto) === 1 => 'cuadrilla',
            preg_match('/\bpor\s+(?:cada\s+)?cargo\b|\bde cada cargo\b/u', $texto) === 1 => 'cargo',
            preg_match('/\bpor\s+(?:cada\s+)?conservacion\b|\bde cada conservacion\b/u', $texto) === 1 => 'conservacion',
            default => null,
        };
    }

    /**
     * @return array{limite?: int, orden?: string}
     */
    private function ordenYLimite(string $texto): array
    {
        $parametros = [];

        if (preg_match('/\b(?:los|las|top|primeros|primeras)\s+(\d{1,2}|[a-z]+)\b/u', $texto, $m) === 1
            && ($cantidad = $this->numero($m[1])) !== null) {
            $parametros['limite'] = max(1, min(50, $cantidad));
        }

        if (preg_match('/\b(?:el|la)\s+(ultimo|ultima|primero|primera|mas reciente|mas antiguo|mas antigua)\b/u', $texto, $m) === 1) {
            $parametros['limite'] = 1;
            $parametros['orden'] = in_array($m[1], ['primero', 'primera', 'mas antiguo', 'mas antiguo'], true) ? 'asc' : 'desc';
        }

        if (preg_match('/\bmas (?:grande|grandes|amplio|amplios|amplia|amplias)\b|\bmayor (?:superficie|area)\b|\bmas metros\b/u', $texto) === 1) {
            $parametros['orden'] = 'superficie_desc';
        }

        if (preg_match('/\b(?:mas antiguo|mas antiguos|mas viejo|mas viejos)\b/u', $texto) === 1) {
            $parametros['orden'] = 'asc';
        }

        if (preg_match('/\b(?:mas reciente|mas recientes|ultimo|ultimos)\b/u', $texto) === 1 && ! isset($parametros['orden'])) {
            $parametros['orden'] = 'desc';
        }

        if (preg_match('/\b(?:los|las)\s+(?:cinco|diez|tres)\s+(?:mas|mejores)\b/u', $texto) === 1) {
            $palabra = preg_match('/\bcinco\b/u', $texto) === 1 ? 5 : (preg_match('/\bdiez\b/u', $texto) === 1 ? 10 : 3);
            $parametros['limite'] = $palabra;
            $parametros['orden'] = $parametros['orden'] ?? 'superficie_desc';
        }

        return $parametros;
    }

    /**
     * @return array{duracion_min?: int, duracion_max?: int}
     */
    private function duracion(string $texto): array
    {
        $minutos = function (string $numero, string $unidad): ?int {
            $valor = $this->numero($numero);

            if ($valor === null) {
                return null;
            }

            return str_starts_with($unidad, 'hora') ? $valor * 60 : $valor;
        };

        if (preg_match('/\bentre\s+(\d{1,4}|[a-z]+)\s+y\s+(\d{1,4}|[a-z]+)\s+(horas?|minutos?)\b/u', $texto, $m) === 1) {
            $desde = $minutos($m[1], $m[3]);
            $hasta = $minutos($m[2], $m[3]);

            if ($desde !== null && $hasta !== null) {
                return ['duracion_min' => min($desde, $hasta), 'duracion_max' => max($desde, $hasta)];
            }
        }

        if (preg_match('/\b(?:mas de|mayor a|mayor de|superior a)\s+(\d{1,4}|[a-z]+)\s*(horas?|minutos?)\b/u', $texto, $m) === 1
            && ($valor = $minutos($m[1], $m[2])) !== null) {
            return ['duracion_min' => $valor];
        }

        if (preg_match('/\b(?:menos de|menor a|menor de|hasta)\s+(\d{1,4}|[a-z]+)\s*(horas?|minutos?)\b/u', $texto, $m) === 1
            && ($valor = $minutos($m[1], $m[2])) !== null) {
            return ['duracion_max' => $valor];
        }

        return [];
    }

    /**
     * Tipo de mantenimiento reconocido en el catálogo real y en sus sinónimos.
     */
    private function tipoMantenimiento(string $texto): ?string
    {
        $tipos = MaintenanceType::query()->pluck('name')
            ->merge(MaintenanceRecord::query()->whereNotNull('maintenance_type')->distinct()->pluck('maintenance_type'))
            ->filter(fn ($nombre) => is_string($nombre) && trim($nombre) !== '')
            ->unique()
            ->values();

        $sinonimos = [
            'riego' => ['riego', 'riegos', 'regar', 'regado'],
            'poda' => ['poda', 'podas', 'podar', 'podado'],
            'limpieza' => ['limpieza', 'limpiezas', 'limpiar', 'barrido', 'barrida'],
            'corte de cesped' => ['corte de cesped', 'cortar cesped', 'cesped', 'grama'],
            'fertilizacion' => ['fertilizacion', 'abono', 'abonado', 'fertilizar'],
            'fumigacion' => ['fumigacion', 'fumigar', 'plaguicida'],
            'siembra' => ['siembra', 'sembrar', 'plantado', 'plantacion'],
            'trasplante' => ['trasplante', 'trasplantar'],
            'pintura' => ['pintura', 'pintado', 'pintar'],
            'reparacion' => ['reparacion', 'reparar', 'arreglo'],
        ];

        $mejor = null;
        $mejorLargo = 0;

        foreach ($tipos as $tipo) {
            $normalizado = $this->normalizador->normalizar($tipo);
            $variantes = $this->variantes($normalizado);

            foreach ($variantes as $variante) {
                if ($variante !== '' && Diccionario::coincide($texto, [$variante]) && strlen($variante) > $mejorLargo) {
                    $mejor = $tipo;
                    $mejorLargo = strlen($variante);
                }
            }

            foreach ($sinonimos as $raiz => $palabras) {
                if ($mejor !== null) {
                    continue;
                }

                if (! str_contains($normalizado, $raiz)) {
                    continue;
                }

                foreach ($palabras as $palabra) {
                    if (Diccionario::coincide($texto, [$palabra])) {
                        $mejor = $tipo;
                        $mejorLargo = strlen($palabra);
                        break;
                    }
                }
            }
        }

        return $mejor;
    }

    /**
     * Palabra de un tipo de trabajo que no existe en el catálogo real.
     */
    private function tipoDesconocido(string $texto): ?string
    {
        $palabras = [
            'riego', 'riegos', 'regar', 'poda', 'podas', 'podar', 'limpieza', 'limpiezas', 'barrido',
            'corte de cesped', 'cesped', 'grama', 'fertilizacion', 'abono', 'fumigacion', 'siembra',
            'trasplante', 'pintura', 'reparacion',
        ];

        foreach ($palabras as $palabra) {
            if (Diccionario::coincide($texto, [$palabra])) {
                return $palabra;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function variantes(string $texto): array
    {
        $variantes = [$texto];

        if (str_ends_with($texto, 'z')) {
            $variantes[] = substr($texto, 0, -1).'ces';
        } elseif (preg_match('/[aeiou]$/u', $texto) === 1) {
            $variantes[] = $texto.'s';
        }

        if (str_ends_with($texto, 'es')) {
            $variantes[] = substr($texto, 0, -2);
        } elseif (str_ends_with($texto, 's')) {
            $variantes[] = substr($texto, 0, -1);
        }

        return array_values(array_unique(array_filter($variantes)));
    }

    /**
     * Primer valor del catálogo que aparece en el texto, priorizando el más largo.
     *
     * @param  list<string>  $valores
     */
    private function valorCatalogo(string $texto, array $valores): ?string
    {
        $mejor = null;
        $mejorLargo = 0;

        foreach ($valores as $valor) {
            $normalizado = $this->normalizador->normalizar($valor);

            if ($normalizado === '') {
                continue;
            }

            foreach ($this->variantes($normalizado) as $variante) {
                if (Diccionario::coincide($texto, [$variante]) && strlen($variante) > $mejorLargo) {
                    $mejor = $valor;
                    $mejorLargo = strlen($variante);
                }
            }
        }

        return $mejor;
    }

    /**
     * @return list<string>
     */
    private function valores(string $columna, string $tabla = 'parks'): array
    {
        return DB::table($tabla)
            ->when(\Illuminate\Support\Facades\Auth::hasUser(), fn ($query) => $query->where('owner_id', \Illuminate\Support\Facades\Auth::id()))
            ->whereNotNull($columna)
            ->where($columna, '<>', '')
            ->distinct()
            ->orderBy($columna)
            ->pluck($columna)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tiposArea(): array
    {
        return GreenAreaType::query()->pluck('name')
            ->merge(Park::query()->whereNotNull('green_area_type')->distinct()->pluck('green_area_type'))
            ->filter(fn ($nombre) => is_string($nombre) && trim($nombre) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function numeroDecimal(string $valor): float
    {
        $limpio = str_replace(['.', ','], ['', '.'], $valor);

        return (float) $limpio;
    }

    /**
     * Coincidencia por raíz para cargos con género distinto ("jardinero" /
     * "jardinera") que no comparten la forma exacta.
     *
     * @param  list<string>  $valores
     */
    private function valorPorRaiz(string $texto, array $valores): ?string
    {
        $mejor = null;
        $mejorLargo = 0;

        foreach ($valores as $valor) {
            $normalizado = $this->normalizador->normalizar($valor);

            if (strlen($normalizado) < 5) {
                continue;
            }

            $raiz = substr($normalizado, 0, 5);

            if (preg_match('/\b'.preg_quote($raiz, '/').'[a-z]*\b/u', $texto) === 1 && strlen($raiz) > $mejorLargo) {
                $mejor = $valor;
                $mejorLargo = strlen($raiz);
            }
        }

        return $mejor;
    }

    /**
     * Palabras que forman nombres de zona ("Norte", "Sur"): no deben confundirse
     * con el nombre de un parque al interpretar "zona Norte".
     *
     * @return array<string, true>
     */
    private function palabrasZona(): array
    {
        $palabras = [];

        foreach ($this->valores('zone') as $zona) {
            foreach ($this->normalizador->palabras($this->normalizador->normalizar($zona)) as $palabra) {
                if (strlen($palabra) >= 3) {
                    $palabras[$palabra] = true;
                }
            }
        }

        return $palabras;
    }

    private function quitarFiltro(string $texto): ?string
    {
        return match (true) {
            preg_match('/\b(?:quita|quitale)\b.*\b(?:fecha|periodo)\b|\bsin filtro de fecha\b/u', $texto) === 1 => 'periodo',
            preg_match('/\b(?:quita|quitale)\b.*\bparque\b/u', $texto) === 1 => 'parque',
            preg_match('/\b(?:quita|quitale)\b.*\bestado\b/u', $texto) === 1 => 'estado',
            preg_match('/\b(?:quita|quitale)\b.*\btipo\b/u', $texto) === 1 => 'tipo_mantenimiento',
            preg_match('/\b(?:quita|quitale)\b.*\btrabajador\b/u', $texto) === 1 => 'trabajador',
            preg_match('/\bsin filtros?\b|\bquita el filtro\b|\bquitale el filtro\b/u', $texto) === 1 => 'todos',
            default => null,
        };
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: ?string, etiqueta: string}>
     */
    private function catalogoParques(): array
    {
        return Park::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn (Park $park) => [
                'id' => $park->id,
                'nombre' => $this->normalizador->normalizar($park->name),
                'codigo' => $park->code ? $this->normalizador->normalizar($park->code) : null,
                'etiqueta' => $park->name,
            ])->all();
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: ?string, etiqueta: string}>
     */
    private function catalogoTrabajadores(): array
    {
        return Worker::query()->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'nombre' => $this->normalizador->normalizar($worker->full_name),
                'codigo' => $worker->code ? $this->normalizador->normalizar($worker->code) : null,
                'etiqueta' => $worker->full_name,
            ])->all();
    }
}
