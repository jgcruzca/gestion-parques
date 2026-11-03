<?php

namespace App\Services\Asistente;

/**
 * Normaliza el texto del usuario solo para compararlo; nunca modifica registros.
 */
class NormalizadorTexto
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ];

    public function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, self::ACENTOS);

        // La puntuación y los símbolos (incluidas las etiquetas HTML) pasan a ser espacios.
        $texto = preg_replace('/[^a-z0-9\/\-\s]/u', ' ', $texto) ?? '';

        // "/" y "-" solo se conservan entre letras o números (fechas y códigos como PRQ-0005).
        $texto = preg_replace('/(?<![a-z0-9])[\/\-]+|[\/\-]+(?![a-z0-9])/u', ' ', $texto) ?? '';

        // Abreviatura informal: "q" por "que", solo como palabra suelta.
        // No toca códigos como PRQ-0005 ni nombres que contengan la letra.
        $texto = preg_replace('/(?<![\w-])q(?![\w-])/u', 'que', $texto) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }

    /**
     * @return list<string>
     */
    public function palabras(string $normalizado): array
    {
        return $normalizado === '' ? [] : explode(' ', $normalizado);
    }
}
