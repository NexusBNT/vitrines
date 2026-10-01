<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<title>Pages · {{ $site->brief['business_name'] ?? $site->slug }}</title>
@viteReactRefresh
@vite('resources/js/editor/main.jsx')
</head>
<body>
<div id="editor-root" data-api="{{ route('filament.admin.sites.editor-api.bootstrap', $site) }}"></div>
</body>
</html>
