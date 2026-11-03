<?php

namespace App\Services\Asistente;

/**
 * Diccionario de sinónimos y expresiones del asistente. Todas las frases están
 * ya normalizadas (minúsculas y sin tildes). Para ampliar el vocabulario basta
 * con agregar una variante en la lista correspondiente.
 */
final class Diccionario
{
    public const MODULOS = [
        'mantenimientos' => [
            'mantenimiento', 'mantenimientos', 'labor', 'labores', 'intervencion', 'intervenciones',
            'orden de trabajo', 'ordenes de trabajo', 'ordenes', 'chamba', 'chambas', 'trabajo', 'trabajos',
            'tarea', 'tareas',
        ],
        'trabajadores' => [
            'trabajador', 'trabajadores', 'personal', 'operario', 'operarios', 'empleado', 'empleados',
            'obrero', 'obreros', 'cuadrilla', 'cuadrillas', 'jardinero', 'jardineros',
        ],
        'parques' => ['parque', 'parques', 'area verde', 'areas verdes'],
        'dashboard' => ['dashboard', 'panel', 'tablero', 'resumen', 'indicadores', 'indicador', 'kpi', 'metricas'],
        'reportes' => ['reporte', 'reportes', 'informe', 'informes'],
        'configuracion' => ['configuracion', 'configuraciones', 'ajustes', 'catalogos'],
    ];

    public const AYUDA = [
        'ayuda', 'ayudame', 'que puedes hacer', 'que sabes hacer', 'como funciona', 'comandos',
        'opciones', 'que haces', 'instrucciones',
    ];

    public const NAVEGAR = [
        'llevame', 'llevarme', 'lleva me', 'abre', 'abreme', 'abrir', 'navega', 'navegar', 'anda', 'vamos',
        'redirigeme', 'dirigeme', 'ir a', 'ir al', 've a', 've al', 'voy a', 'vayamos a', 'quiero ir',
    ];

    public const REPORTE = ['reporte', 'reportes', 'reportito', 'reportitos', 'informe', 'informes'];

    public const GENERAR = [
        'genera', 'generar', 'generame', 'generas', 'genere', 'sacame', 'saca', 'sacas', 'sacar', 'sacarme',
        'preparame', 'prepara', 'preparas', 'descarga', 'descargar', 'descargas', 'exporta', 'exportar',
        'exportas', 'imprime', 'imprimes', 'emite', 'emites', 'elabora', 'elaborar',
        'elaborame', 'confecciona', 'confeccionar',
    ];

    public const CONTAR = ['cuantos', 'cuantas', 'cuanto', 'cantidad', 'numero de', 'total de', 'contar', 'cuenta'];

    public const BUSCAR = ['busca', 'buscar', 'buscame', 'encuentra', 'encuentrame', 'localiza', 'donde esta', 'donde queda', 'ubica'];

    public const LISTAR = [
        'lista', 'listar', 'listame', 'muestrame', 'muestra', 'mostrar', 'dame', 'damelo', 'damelos', 'dime',
        'ver', 'cuales', 'pasame', 'pasamelo', 'pasamelos', 'pasas', 'pasa', 'pasanos', 'necesito',
        'necesitamos', 'quiero', 'queremos', 'quisiera', 'queria', 'me gustaria',
    ];

    public const ACTIVIDADES = ['actividad', 'actividades'];

    /** Preguntas sobre el punto de agua de los parques (no sobre riego de mantenimiento). */
    public const AGUA = [
        'punto de agua', 'puntos de agua', 'toma de agua', 'tomas de agua', 'conexion de agua',
        'abastecimiento', 'abastecimiento de agua', 'cobertura de agua', 'suministro de agua',
        'red publica', 'pozo', 'cisterna', 'camion cisterna', 'sin agua', 'con agua',
        'tienen agua', 'tiene agua', 'no tienen agua', 'no tiene agua', 'falta de agua',
        'se abastece', 'abastece de agua', 'como se abastece',
    ];

    /** Consultas sobre la programación periódica de un parque. */
    public const PROGRAMACION = [
        'programacion', 'programaciones', 'frecuencia', 'frecuencias', 'cada cuantos dias', 'cada cuanto',
        'cada cuantos', 'proxima fecha', 'siguiente fecha', 'cuando toca', 'cuando se hace',
        'cuando se hizo por ultima vez', 'ultima vez', 'regla de mantenimiento', 'reglas de mantenimiento',
        'catalogo de reglas',
    ];

    /** Consultas sobre los motivos de retraso y sus documentos de respaldo. */
    public const JUSTIFICACIONES = [
        'motivo de retraso', 'motivos de retraso', 'motivo del retraso', 'motivos del retraso',
        'justificacion', 'justificaciones', 'justificado', 'justificados', 'justificada', 'justificadas',
        'documento de respaldo', 'documentos de respaldo', 'respaldo', 'respaldos',
        'explicacion del retraso', 'explicaciones de retraso', 'auditoria de vencimientos',
    ];

    /** Consultas sobre catálogos y datos institucionales. */
    public const CATALOGOS = [
        'tipo de area verde', 'tipos de area verde', 'tipos de areas verdes', 'tipo de mantenimiento',
        'tipos de mantenimiento', 'catalogo', 'catalogos', 'informacion institucional',
        'datos de la municipalidad', 'municipalidad', 'datos de la organizacion', 'organizacion', 'area responsable', 'responsable del area',
        'ano de gestion', 'pie de documento',
    ];

    /** Solicita un resumen agrupado en lugar de un listado de órdenes. */
    public const AGRUPAR = [
        'por tipo', 'por tipos', 'por cada tipo', 'de cada tipo', 'agrupado', 'agrupados', 'agrupada',
        'agrupadas', 'agrupar', 'agrupame', 'resumen por', 'resumido por', 'desglose', 'desglosado',
        'cuantos de cada', 'cuantas de cada', 'cantidad por', 'totales por', 'por estado', 'por parque',
        'por trabajador', 'por zona', 'por mes', 'por cuadrilla', 'por cargo', 'ranking', 'comparar',
        'comparacion', 'compara',
    ];

    /** Pide el detalle de una orden concreta. */
    public const DETALLE = [
        'detalle del mantenimiento', 'detalle de la orden', 'detalles del mantenimiento',
        'detalles de la orden', 'ficha del mantenimiento', 'ficha de la orden', 'parte de mantenimiento',
        'abrir la orden', 'abre la orden', 'abrir el mantenimiento', 'abre el mantenimiento',
    ];

    public const ORIGEN = [
        'extraordinario' => ['extraordinario', 'extraordinarios', 'extraordinaria', 'extraordinarias', 'adicional', 'adicionales', 'no programado', 'no programados'],
        'periodico' => ['periodico', 'periodicos', 'periodica', 'periodicas', 'ordinario', 'ordinarios', 'ordinaria', 'ordinarias'],
    ];

    public const ASIGNACION = [
        'sin_asignar' => ['sin asignar', 'sin responsable', 'por asignar', 'no asignado', 'no asignados', 'sin trabajador', 'sin personal'],
        'asignado' => ['asignado', 'asignados', 'asignada', 'asignadas', 'con responsable', 'con trabajador'],
    ];

    public const CONSERVACION = [
        'bueno' => ['bueno', 'buena', 'buen estado', 'optimo', 'optima'],
        'regular' => ['regular', 'estado regular'],
        'malo' => ['malo', 'mala', 'mal estado', 'deteriorado', 'deteriorada'],
    ];

    public const EXISTENCIA = [
        'con_documento' => ['con documento', 'con documentos', 'tiene documento', 'tienen documento', 'con respaldo', 'con adjunto'],
        'sin_documento' => ['sin documento', 'sin documentos', 'no tiene documento', 'no tienen documento', 'sin respaldo', 'sin adjunto'],
        'con_foto' => ['con foto', 'con fotos', 'con fotografia', 'con fotografias', 'tiene foto', 'tienen foto'],
        'sin_foto' => ['sin foto', 'sin fotos', 'sin fotografia', 'sin fotografias', 'no tiene foto', 'no tienen foto'],
        'con_ubicacion' => ['con ubicacion', 'con coordenadas', 'en el mapa', 'geolocalizado', 'geolocalizados'],
        'sin_ubicacion' => ['sin ubicacion', 'sin coordenadas', 'sin mapa', 'sin geolocalizar'],
        'con_observaciones' => ['con observaciones', 'con observacion', 'tiene observaciones'],
        'sin_observaciones' => ['sin observaciones', 'sin observacion'],
    ];

    public const DIAS_SEMANA = [
        'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4,
        'viernes' => 5, 'sabado' => 6, 'domingo' => 7,
    ];

    public const NUMEROS_PALABRA = [
        'un' => 1, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'quince' => 15,
        'veinte' => 20, 'treinta' => 30,
    ];

    public const PENDIENTES = [
        'pendiente', 'pendientes', 'por atender', 'por hacer', 'sin atender', 'falta', 'faltan', 'falto',
        'faltaron', 'faltante', 'faltantes', 'sin hacer', 'sin terminar', 'por terminar', 'por completar',
        'vencido', 'vencidos', 'vencida', 'vencidas', 'atrasado', 'atrasados', 'retrasado', 'retrasados',
    ];

    /** Quitar un filtro del tema anterior sin empezar de cero. */
    public const QUITAR_FILTRO = [
        'quitale la fecha', 'quitale el periodo', 'quita la fecha', 'quita el periodo', 'sin filtro de fecha',
        'quita el parque', 'quitale el parque', 'quita el estado', 'quitale el estado',
        'quita el tipo', 'quitale el tipo', 'quita el trabajador', 'quitale el trabajador', 'quita el filtro',
        'quitale el filtro', 'sin filtros', 'sin filtro',
    ];

    public const ESCRITURA_DESTRUCTIVA = [
        'elimina', 'elimine', 'eliminar', 'eliminame', 'borra', 'borrar', 'borre', 'destruye', 'quita',
        'quitar', 'remueve', 'remover', 'vacia', 'vaciar',
    ];

    public const ESCRITURA = [
        'crea', 'crear', 'creame', 'agrega', 'agregar', 'anade', 'anadir', 'edita', 'editar', 'modifica',
        'modificar', 'actualiza', 'actualizar', 'cambia', 'cambiar', 'registra', 'registrar', 'registrame',
        'programa', 'programar', 'inicia', 'iniciar', 'finaliza', 'finalizar', 'cancela', 'cancelar',
        'asigna', 'asignar', 'activa', 'activar', 'desactiva', 'desactivar', 'guarda', 'guardar',
        'inserta', 'insertar', 'agendar',
    ];

    public const NO_DISPONIBLE = [
        'prediccion', 'predicciones', 'predice', 'predecir', 'pronostico', 'pronosticar', 'plaga', 'plagas',
        'clima', 'diagnostico', 'diagnosticar', 'recomienda', 'recomendacion', 'recomendaciones', 'causa',
        'causas', 'por que', 'analiza', 'analisis', 'tendencia', 'tendencias', 'estima', 'estimar',
        'proyecta', 'proyeccion',
    ];

    public const CANCELAR_CONTEXTO = [
        'olvidalo', 'olvida', 'dejalo', 'deja', 'nada', 'no gracias', 'cancelar', 'cancela', 'cancelalo',
        'mejor no', 'salir', 'no importa',
    ];

    /** Confirmaciones que mantienen el tema actual sin cambiar sus filtros ("solo esos"). */
    public const CONTINUAR = [
        'solo esos', 'solo esas', 'esos', 'esas', 'esos nomas', 'esas nomas', 'solo esos nomas',
        'solo esas nomas', 'asi esta bien', 'asi esta', 'esta bien', 'correcto', 'exacto',
    ];

    /** Se evalúan en este orden: las variantes "pasado/a" antes que las actuales. */
    public const PERIODOS = [
        'anteayer' => ['antes de ayer', 'anteayer', 'antier', 'hace dos dias'],
        'pasado_manana' => ['pasado manana', 'despues de manana'],
        'semana_pasada' => ['semana pasada', 'semana anterior', 'la semana que paso', 'la semana anterior'],
        'proxima_semana' => ['proxima semana', 'semana que viene', 'la semana que viene', 'semana siguiente'],
        'mes_pasado' => ['mes pasado', 'mes anterior', 'el mes anterior'],
        'proximo_mes' => ['proximo mes', 'mes que viene', 'el mes que viene', 'mes siguiente'],
        'anio_pasado' => ['ano pasado', 'ano anterior', 'el ano pasado'],
        'proximo_anio' => ['proximo ano', 'ano que viene', 'el ano que viene', 'ano siguiente'],
        'semana_actual' => ['esta semana', 'semana actual', 'semanal', 'de la semana', 'en la semana', 'semana en curso', 'lo que va de la semana', 'en lo que va de la semana'],
        'mes_actual' => ['este mes', 'mes actual', 'mensual', 'del mes', 'en el mes', 'mes en curso', 'lo que va del mes', 'en lo que va del mes'],
        'anio_actual' => ['este ano', 'ano actual', 'lo que va del ano', 'en lo que va del ano', 'ano en curso'],
        'quincena_actual' => ['esta quincena', 'quincena actual'],
        'trimestre_actual' => ['este trimestre', 'trimestre actual'],
        'semestre_actual' => ['este semestre', 'semestre actual'],
        'manana' => ['manana'],
        'ayer' => ['ayer'],
        'hoy' => ['hoy'],
        'todo' => ['todo el historial', 'toda la historia', 'todas las fechas', 'sin filtro de fecha', 'desde siempre'],
    ];

    public const ESTADOS = [
        'finalizado' => [
            'realizado', 'realizados', 'realizada', 'realizadas', 'hecho', 'hechos', 'hecha', 'hechas',
            'ejecutado', 'ejecutados', 'ejecutada', 'ejecutadas', 'finalizado', 'finalizados', 'finalizada',
            'finalizadas', 'completado', 'completados', 'completada', 'completadas', 'terminado', 'terminados',
            'terminada', 'terminadas', 'concluido', 'concluidos', 'culminado', 'culminados', 'efectuado',
            'efectuados', 'realizaron', 'hicieron', 'ejecutaron', 'completaron', 'finalizaron', 'terminaron',
            'acabado', 'acabados', 'acabada', 'acabadas', 'se hizo', 'se hicieron', 'se termino',
            'se terminaron', 'se finalizo', 'se finalizaron',
        ],
        'vencido' => [
            'vencido', 'vencidos', 'vencida', 'vencidas', 'atrasado', 'atrasados', 'retrasado', 'retrasados',
            'fuera de plazo', 'pasado de fecha', 'pasados de fecha', 'pasada de fecha', 'pasadas de fecha',
            'fuera de fecha', 'se vencio', 'se vencieron',
        ],
        'proximo' => ['proximo', 'proximos', 'por vencer', 'por empezar', 'por iniciar'],
        'en_proceso' => ['en proceso', 'en curso', 'en ejecucion', 'en mantenimiento', 'iniciado', 'iniciados', 'en marcha', 'se esta haciendo', 'se estan haciendo'],
        'cancelado' => [
            'cancelado', 'cancelados', 'cancelada', 'canceladas', 'anulado', 'anulados', 'suspendido',
            'suspendidos', 'suspendida', 'suspendidas', 'se cancelo', 'se cancelaron', 'se anulo', 'se anularon',
        ],
        'por_atender' => [
            'pendiente', 'pendientes', 'por atender', 'por hacer', 'sin atender', 'falta', 'faltan', 'faltante',
            'faltantes', 'sin hacer', 'sin terminar', 'por terminar', 'por completar',
        ],
    ];

    public const FORMATOS = [
        'pdf' => ['pdf'],
        'excel' => ['excel', 'xlsx', 'xls', 'csv', 'planilla', 'hoja de calculo'],
    ];

    public const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    private const PALABRAS_VACIAS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'un', 'una', 'unos', 'unas', 'que', 'en', 'por', 'para', 'con',
        'y', 'o', 'a', 'al', 'mi', 'me', 'te', 'se', 'lo', 'le', 'esta', 'este', 'estos', 'estas', 'ese', 'esa',
        'hay', 'tengo', 'tenemos', 'tienes', 'tiene', 'quiero', 'necesito', 'favor', 'porfavor', 'por favor',
        'sistema', 'registrado', 'registrados', 'registrada', 'registradas', 'todo', 'todos', 'toda', 'todas',
        'semana', 'mes', 'pasada', 'pasado', 'actual', 'es', 'son', 'estan', 'esta', 'esten', 'como', 'cual',
        'donde', 'cuando', 'sobre', 'si', 'no', 'mas', 'hola', 'gracias', 'buenos', 'dias', 'tardes', 'noches',
        'hacer', 'dar', 'tener', 'ser', 'estar', 'general', 'completo', 'completa', 'realizar', 'se', 'fue',
        'fueron', 'pasa', 'paso', 'desde', 'hasta', 'entre', 'seria', 'puedes', 'puedo', 'podrias', 'podria',
        'pe', 'p', 'pues', 'porfa', 'porfis', 'nomas', 'ya', 'oye', 'disculpa', 'disculpe', 'permiso',
        'ahora', 'entonces', 'tambien', 'mejor', 'solo', 'solamente', 'pero', 'aqui', 'ahi', 'alli',
        'dia', 'dias', 'fecha', 'fechas', 'manana', 'semanas', 'meses', 'antes', 'hace', 'dos',
        'oe', 'oiga', 'sea', 'historial', 'detalle', 'detalles', 'ficha', 'orden', 'todas', 'total',
        'activo', 'activos', 'activa', 'activas', 'inactivo', 'inactivos', 'inactiva', 'inactivas',
        'cargo', 'cargos', 'posicion', 'superficie', 'superficies', 'metros', 'm2', 'hectareas',
        'foto', 'fotos', 'fotografia', 'fotografias', 'documento', 'documentos', 'adjunto', 'adjuntos',
        'agua', 'punto', 'puntos', 'abastecimiento', 'suministro', 'red', 'publica', 'pozo', 'cisterna',
        'operativo', 'operativos', 'operativa', 'operativas', 'inoperativo', 'inoperativos',
        'conservacion', 'bueno', 'buena', 'regular', 'malo', 'mala', 'zona', 'zonas', 'registro',
    ];

    /**
     * Devuelve true si el texto contiene alguna de las frases como palabra completa.
     *
     * @param  list<string>  $frases
     */
    public static function coincide(string $texto, array $frases): bool
    {
        return preg_match(self::patron($frases), $texto) === 1;
    }

    /**
     * Posición (en bytes) de la primera frase encontrada, o null.
     *
     * @param  list<string>  $frases
     */
    public static function posicion(string $texto, array $frases): ?int
    {
        if (preg_match(self::patron($frases), $texto, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $m[0][1];
    }

    /**
     * Módulos mencionados en el texto con la posición de su primera aparición.
     *
     * @return array<string, int>
     */
    public static function modulosEn(string $texto): array
    {
        $encontrados = [];

        foreach (self::MODULOS as $modulo => $frases) {
            $posicion = self::posicion($texto, $frases);

            if ($posicion !== null) {
                $encontrados[$modulo] = $posicion;
            }
        }

        return $encontrados;
    }

    /**
     * Palabras que pertenecen al vocabulario del asistente. Sirven para no
     * confundirlas con nombres de parques o trabajadores.
     *
     * @return array<string, true>
     */
    public static function vocabulario(): array
    {
        static $vocabulario = null;

        if ($vocabulario !== null) {
            return $vocabulario;
        }

        $grupos = [
            ...array_values(self::MODULOS), self::AYUDA, self::NAVEGAR, self::REPORTE, self::GENERAR, self::CONTAR,
            self::BUSCAR, self::LISTAR, self::ACTIVIDADES, self::PENDIENTES, self::ESCRITURA_DESTRUCTIVA,
            self::ESCRITURA, self::NO_DISPONIBLE, self::CANCELAR_CONTEXTO, self::CONTINUAR, self::QUITAR_FILTRO,
            self::AGUA, self::PROGRAMACION, self::JUSTIFICACIONES, self::CATALOGOS, self::AGRUPAR, self::DETALLE,
            ...array_values(self::ORIGEN), ...array_values(self::ASIGNACION), ...array_values(self::CONSERVACION),
            ...array_values(self::EXISTENCIA), array_keys(self::DIAS_SEMANA), array_keys(self::NUMEROS_PALABRA),
            ...array_values(self::PERIODOS), ...array_values(self::ESTADOS), ...array_values(self::FORMATOS),
            array_keys(self::MESES), self::PALABRAS_VACIAS,
        ];

        $vocabulario = [];

        foreach ($grupos as $frases) {
            foreach ($frases as $frase) {
                foreach (explode(' ', $frase) as $palabra) {
                    $vocabulario[$palabra] = true;
                }
            }
        }

        return $vocabulario;
    }

    /**
     * @param  list<string>  $frases
     */
    private static function patron(array $frases): string
    {
        // Las frases más largas primero, para que "semana pasada" gane a "semana".
        usort($frases, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return '/\b(?:'.implode('|', array_map(fn (string $frase) => preg_quote($frase, '/'), $frases)).')\b/u';
    }
}
