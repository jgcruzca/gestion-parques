<?php

namespace App\Services\Asistente;

/**
 * Conserva, dentro de la sesión, la solicitud que espera una aclaración y el
 * último tema resuelto. Así una respuesta corta ("mantenimientos", "PDF")
 * completa la solicitud pendiente, y un seguimiento ("y los vencidos")
 * conserva el parque, el período o el formato del tema anterior.
 */
class GestorContexto
{
    private const CLAVE = 'asistente.contexto';

    private const CLAVE_HILO = 'asistente.hilo';

    /** Parámetros que se conservan del último tema. */
    private const PERMITIDOS = [
        'modulo', 'periodo', 'desde', 'hasta', 'estado', 'formato', 'parque', 'parques', 'trabajador', 'termino',
        'tipo_mantenimiento', 'origen', 'asignacion', 'duracion_min', 'duracion_max', 'retraso_min',
        'con_documento', 'con_foto', 'agua', 'agua_estado', 'agua_abastecimiento', 'parque_tipo',
        'parque_zona', 'parque_conservacion', 'parque_activo', 'superficie_min', 'superficie_max',
        'con_ubicacion', 'sin_ubicacion', 'con_observaciones', 'sin_observaciones', 'fecha_registro',
        'trabajador_activo', 'trabajador_cargo', 'trabajador_cuadrilla', 'sin_trabajos',
        'agrupar_por', 'limite', 'orden', 'numero_orden', 'orden_id', 'historial', 'busqueda_texto',
        'sin_foto', 'sin_documento', 'comparar', 'todo_el_historial',
    ];

    /**
     * @return array{intencion: string, parametros: array<string, mixed>, pendientes: list<string>, candidatos: array<string, list<array{id: int, nombre: string}>>, creado: int}|null
     */
    public function obtener(): ?array
    {
        $contexto = session(self::CLAVE);

        if (! is_array($contexto)) {
            return null;
        }

        if (time() - ($contexto['creado'] ?? 0) > (int) config('asistente.ttl_contexto')) {
            $this->limpiar();

            return null;
        }

        return $contexto;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @param  list<string>  $pendientes
     * @param  array<string, list<array{id: int, nombre: string}>>  $candidatos
     */
    public function guardar(string $intencion, array $parametros, array $pendientes, array $candidatos = []): void
    {
        // Solo se guardan los parámetros necesarios para completar la solicitud.
        session([self::CLAVE => [
            'intencion' => $intencion,
            'parametros' => array_intersect_key($parametros, array_flip(self::PERMITIDOS)),
            'pendientes' => $pendientes,
            'candidatos' => $candidatos,
            'creado' => time(),
        ]]);
    }

    public function limpiar(): void
    {
        session()->forget(self::CLAVE);
    }

    /**
     * Último tema resuelto, para continuar la conversación ("y los vencidos").
     *
     * @return array{intencion: string, operacion: string, parametros: array<string, mixed>, creado: int}|null
     */
    public function hilo(): ?array
    {
        $hilo = session(self::CLAVE_HILO);

        if (! is_array($hilo)) {
            return null;
        }

        if (time() - ($hilo['creado'] ?? 0) > (int) config('asistente.ttl_hilo')) {
            $this->limpiarHilo();

            return null;
        }

        return $hilo;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function recordar(string $intencion, array $parametros): void
    {
        session([self::CLAVE_HILO => [
            'intencion' => $intencion,
            'operacion' => (string) ($parametros['operacion'] ?? 'consultar'),
            'parametros' => array_intersect_key($parametros, array_flip(self::PERMITIDOS)),
            'creado' => time(),
        ]]);
    }

    public function limpiarHilo(): void
    {
        session()->forget(self::CLAVE_HILO);
    }
}
