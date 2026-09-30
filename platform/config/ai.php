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
            'timeout' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routage par tâche
    |--------------------------------------------------------------------------
    |
    | site_content : rédaction complète des textes du site (pages, SEO, FAQ).
    | alt_text     : description des photos (texte alternatif, usage).
    |
    | « model » à null = modèle par défaut du fournisseur.
    |
    */

    'tasks' => [
        'site_content' => [
            'provider' => env('AI_SITE_CONTENT_PROVIDER', 'claude'),
            'model' => env('AI_SITE_CONTENT_MODEL'),
        ],
        'alt_text' => [
            'provider' => env('AI_ALT_TEXT_PROVIDER', 'claude'),
            'model' => env('AI_ALT_TEXT_MODEL'),
        ],
    ],

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openai'),

    'prompt_version' => 'v1',

];
