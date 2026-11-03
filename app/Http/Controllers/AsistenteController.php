<?php

namespace App\Http\Controllers;

use App\Services\Asistente\Asistente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AsistenteController extends Controller
{
    public function consultar(Request $request, Asistente $asistente): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'mensaje' => ['required', 'string', 'max:'.config('asistente.max_mensaje')],
            'reiniciar' => ['sometimes', 'boolean'],
        ], [
            'mensaje.required' => 'Escribe un mensaje para el asistente.',
            'mensaje.string' => 'El mensaje debe ser texto.',
            'mensaje.max' => 'El mensaje no puede superar los :max caracteres.',
            'reiniciar.boolean' => 'La indicación de reinicio no es válida.',
        ]);

        if ($validador->fails()) {
            return response()->json([
                'estado' => 'error',
                'intencion' => null,
                'mensaje' => $validador->errors()->first(),
                'errores' => $validador->errors()->toArray(),
            ], 422);
        }

        // Evita ejecutar dos veces la misma solicitud mientras la anterior sigue en curso.
        $bloqueo = Cache::lock('asistente:procesando:'.$request->user()->id, 20);

        if (! $bloqueo->get()) {
            return response()->json([
                'estado' => 'error',
                'intencion' => null,
                'mensaje' => 'Sigo procesando tu solicitud anterior. Espera un momento.',
            ], 409);
        }

        try {
            return response()->json($asistente->procesar(
                (string) $validador->validated()['mensaje'],
                $request->boolean('reiniciar'),
            ));
        } catch (Throwable $e) {
            // Solo se registra dónde falló: el mensaje del usuario y los datos no se guardan.
            Log::error('asistente.fallo', ['clase' => $e::class, 'archivo' => basename($e->getFile()), 'linea' => $e->getLine()]);

            return response()->json([
                'estado' => 'error',
                'intencion' => null,
                'mensaje' => 'No pude procesar tu solicitud en este momento. Inténtalo de nuevo; el resto del sistema sigue funcionando.',
            ], 500);
        } finally {
            $bloqueo->release();
        }
    }
}
