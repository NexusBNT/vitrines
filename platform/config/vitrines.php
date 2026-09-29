<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thèmes disponibles (templates/themes)
    |--------------------------------------------------------------------------
    */

    'themes' => [
        'artisan' => 'Artisan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Médias des sites clients
    |--------------------------------------------------------------------------
    |
    | Les originaux sont conservés (pour les sauvegardes) mais jamais servis :
    | seules les variantes ré-encodées sont publiées sur les sites.
    |
    */

    'media' => [
        'disk' => 'media',
        'max_upload_kb' => 20 * 1024,
        'max_pixels' => 50_000_000,
        'accepted_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'widths' => [480, 800, 1200, 1600],
        'quality' => [
            'avif' => 55,
            'webp' => 75,
            'jpg' => 80,
        ],
    ],

];
