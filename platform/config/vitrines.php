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

    'templates_path' => env('VITRINES_TEMPLATES_PATH', base_path('../templates')),

    /*
    |--------------------------------------------------------------------------
    | Builds des sites statiques
    |--------------------------------------------------------------------------
    */

    'builds_path' => env('VITRINES_DATA_PATH') ? env('VITRINES_DATA_PATH').'/builds' : storage_path('app/builds'),

    'keep_preview_builds' => 3,

    /*
    | Point de réception des formulaires de contact des sites (lot 6).
    */

    'forms_endpoint' => env('VITRINES_FORMS_ENDPOINT', 'https://api.example.test'),

    /*
    |--------------------------------------------------------------------------
    | Mentions légales
    |--------------------------------------------------------------------------
    |
    | « operator » : la société qui conçoit et maintient les sites (vous).
    | « host » : l'hébergeur physique des fichiers, obligatoire dans les mentions légales.
    |
    */

    'operator' => [
        'name' => env('VITRINES_OPERATOR_NAME', 'Vitrines'),
        'url' => env('VITRINES_OPERATOR_URL'),
    ],

    'host' => [
        'name' => env('VITRINES_HOST_NAME', 'OVH SAS'),
        'address' => env('VITRINES_HOST_ADDRESS', '2 rue Kellermann, 59100 Roubaix, France'),
        'url' => env('VITRINES_HOST_URL', 'https://www.ovhcloud.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Types Schema.org selon l'activité
    |--------------------------------------------------------------------------
    |
    | Mot-clé recherché dans l'activité saisie => sous-type de LocalBusiness.
    | Le premier mot-clé trouvé l'emporte ; à défaut : LocalBusiness.
    |
    */

    'schema_types' => [
        'plombier' => 'Plumber',
        'chauffagiste' => 'HVACBusiness',
        'climatisation' => 'HVACBusiness',
        'electricien' => 'Electrician',
        'couvreur' => 'RoofingContractor',
        'toiture' => 'RoofingContractor',
        'peintre' => 'HousePainter',
        'serrurier' => 'Locksmith',
        'menuisier' => 'GeneralContractor',
        'charpentier' => 'GeneralContractor',
        'macon' => 'GeneralContractor',
        'renovation' => 'GeneralContractor',
        'carreleur' => 'GeneralContractor',
        'paysagiste' => 'HomeAndConstructionBusiness',
        'jardinier' => 'HomeAndConstructionBusiness',
        'demenage' => 'MovingCompany',
        'coiffeu' => 'HairSalon',
        'barbier' => 'HairSalon',
        'estheti' => 'BeautySalon',
        'institut' => 'BeautySalon',
        'garage' => 'AutoRepair',
        'mecani' => 'AutoRepair',
        'carross' => 'AutoBodyShop',
        'boulang' => 'Bakery',
        'patiss' => 'Bakery',
        'restaurant' => 'Restaurant',
        'traiteur' => 'FoodEstablishment',
        'photograph' => 'ProfessionalService',
        'avocat' => 'LegalService',
        'comptab' => 'AccountingService',
        'osteo' => 'MedicalBusiness',
        'kine' => 'MedicalBusiness',
        'nettoyage' => 'HomeAndConstructionBusiness',
        'informatique' => 'ProfessionalService',
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
