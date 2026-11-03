<?php

/**
 * Punto de entrada serverless para Vercel (runtime vercel-php).
 * El sistema de archivos de la función es de solo lectura salvo /tmp, por lo que
 * el almacenamiento temporal de Laravel (vistas compiladas, logs, caché) se redirige allí.
 * Los archivos subidos por los usuarios van a Cloudflare R2, nunca al disco local.
 */
$tmp = '/tmp/storage';

foreach (['app/public', 'framework/views', 'framework/cache/data', 'framework/sessions', 'logs'] as $dir) {
    if (! is_dir("$tmp/$dir")) {
        @mkdir("$tmp/$dir", 0777, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $tmp;
putenv("LARAVEL_STORAGE_PATH=$tmp");

require __DIR__.'/../public/index.php';
