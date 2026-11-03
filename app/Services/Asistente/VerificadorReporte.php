<?php

namespace App\Services\Asistente;

use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Genera el documento con el controlador de reportes existente, antes de
 * ofrecer el enlace, para no entregar nunca una descarga que falle.
 */
class VerificadorReporte
{
    /**
     * @param  array<string, string>  $consulta  Parámetros del reporte (mismos que usa /reportes/exportar).
     *
     * @throws RuntimeException si el documento no se puede generar.
     */
    public function verificar(string $formato, array $consulta): void
    {
        $ruta = route($formato === 'pdf' ? 'reports.pdf' : 'reports.csv', $consulta, false);
        $controlador = app(ReportController::class);
        $peticion = Request::create($ruta, 'GET');

        if ($formato === 'pdf') {
            $respuesta = $controlador->pdf($peticion);

            if ($respuesta->getStatusCode() !== 200 || ! str_starts_with((string) $respuesta->getContent(), '%PDF-')) {
                throw new RuntimeException('El generador de PDF no devolvió un documento válido.');
            }

            return;
        }

        $respuesta = $controlador->csv($peticion);

        ob_start();
        $respuesta->sendContent();
        $contenido = (string) ob_get_clean();

        if ($respuesta->getStatusCode() !== 200 || $contenido === '') {
            throw new RuntimeException('El generador de CSV no devolvió un documento válido.');
        }
    }
}
