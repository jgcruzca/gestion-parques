<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria del asistente
    |--------------------------------------------------------------------------
    | Solo se usa para resolver "hoy", "ayer", "esta semana", etc. El resto del
    | sistema conserva APP_TIMEZONE.
    */
    'timezone' => env('ASISTENTE_TIMEZONE', 'America/Lima'),

    // Longitud máxima del mensaje del usuario.
    'max_mensaje' => 500,

    // Solicitudes permitidas por usuario y por minuto.
    'limite_por_minuto' => 30,

    // Registros máximos que se listan en una respuesta del chat.
    'max_items' => 10,

    // Segundos que se conserva una aclaración pendiente.
    'ttl_contexto' => 600,

    // Segundos que se recuerda el último tema para continuar la conversación.
    'ttl_hilo' => 1800,

];
