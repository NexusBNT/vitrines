<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fournisseurs d'IA
    |--------------------------------------------------------------------------
    |
    | Les clés restent dans .env. Un fournisseur sans clé est considéré comme
    | indisponible : les tâches qui l'utilisent basculent sur le fournisseur de
    | repli, ou échouent avec un message clair.
    |
    */

    'providers' => [
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
            'effort' => env('ANTHROPIC_EFFORT', 'medium'),
            'timeout' => 300,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_MODEL'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
            'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
            'timeout' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routage par tâche
    |--------------------------------------------------------------------------
    |
    | site_content : rédaction complète des textes du site (pages, SEO, FAQ).
    | design       : propositions de direction artistique (jetons de design).
    | image_prompts: description des illustrations à générer (offre avec génération intégrale).
    | alt_text     : description des photos (texte alternatif, usage).
    | editor       : assistant de l'éditeur de pages (réécriture, rédaction de blocs, référencement).
    |
    | « model » à null = modèle par défaut du fournisseur.
    |
    */

    'tasks' => [
        'site_content' => [
            'provider' => env('AI_SITE_CONTENT_PROVIDER', 'claude'),
            'model' => env('AI_SITE_CONTENT_MODEL'),
        ],
        'design' => [
            'provider' => env('AI_DESIGN_PROVIDER', 'openai'),
            'model' => env('AI_DESIGN_MODEL'),
        ],
        'image_prompts' => [
            'provider' => env('AI_IMAGE_PROMPTS_PROVIDER', 'openai'),
            'model' => env('AI_IMAGE_PROMPTS_MODEL'),
        ],
        'alt_text' => [
            'provider' => env('AI_ALT_TEXT_PROVIDER', 'claude'),
            'model' => env('AI_ALT_TEXT_MODEL'),
        ],
        'editor' => [
            'provider' => env('AI_EDITOR_PROVIDER', 'claude'),
            'model' => env('AI_EDITOR_MODEL'),
        ],
    ],

    /*
    | Illustrations générées (offre Pro+) : nombre maximal d'illustrations de services par site.
    */

    'max_service_images' => (int) env('AI_MAX_SERVICE_IMAGES', 4),

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openai'),

    'prompt_version' => 'v1',

];
