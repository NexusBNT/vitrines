@php
    /** @var \App\Domain\Build\RenderContext $ctx */
    $site = $ctx->site();
@endphp
<!doctype html>
<html lang="fr" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $page['title'] }}</title>
<meta name="description" content="{{ $page['meta_description'] }}">
@if (! $ctx->target->indexable() || ($page['noindex'] ?? false))
<meta name="robots" content="noindex, nofollow">
@endif
<link rel="canonical" href="{{ $ctx->absoluteUrl($page['key']) }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="{{ $site['name'] }}">
<meta property="og:title" content="{{ $page['title'] }}">
<meta property="og:description" content="{{ $page['meta_description'] }}">
<meta property="og:url" content="{{ $ctx->absoluteUrl($page['key']) }}">
@if ($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
<meta name="twitter:card" content="summary_large_image">
@endif
<meta name="theme-color" content="{{ $palette['primary'] }}">
<link rel="icon" href="{{ $ctx->asset('favicon') }}" type="image/svg+xml">
@foreach ($ctx->preloadFonts() as $font)
<link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
@endforeach
<link rel="stylesheet" href="{{ $ctx->asset('css') }}">
<script src="{{ $ctx->asset('js') }}" defer></script>
{{ $structuredData }}
</head>
<body class="{{ \App\Domain\Sites\Design::bodyClasses($design) }} page-{{ $page['key'] }}">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('site::themes.artisan.partials.header')
<main id="contenu">
@foreach ($page['sections'] as $section)
@includeFirst(['site::themes.artisan.sections.'.$section['type'], 'site::shared.sections.'.$section['type']], ['section' => $section, 'index' => $loop->index])
@endforeach
</main>
@include('site::themes.artisan.partials.footer')
</body>
</html>
