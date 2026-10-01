@php
    $variant = $ctx->media($section['image'] ?? null) ? ($section['variant'] ?? 'split') : 'plain';
    $background = in_array($variant, ['image', 'boxed'], true);
@endphp
<section class="hero hero--{{ str_replace('_', '-', $variant) }}{{ $variant === 'boxed' ? ' hero--image' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    @if ($background)
        <div class="hero-bg">{{ $ctx->picture($section['image'], '100vw', priority: true) }}</div>
    @endif
    <div class="container hero-inner">
        <div class="hero-text">
            <h1>{{ $section['h1'] }}</h1>
            @if (! empty($section['lead']))
                <p class="lead">{{ $section['lead'] }}</p>
            @endif
            <div class="actions">
                @if ($href = $ctx->phoneHref($site['phone']))
                    <a class="button button-primary" href="{{ $href }}" data-track="tel">Appeler le {{ $site['phone'] }}</a>
                @endif
                <a class="button button-secondary" href="{{ $ctx->contactUrl() }}">{{ $section['cta_label'] ?? 'Demander un contact' }}</a>
            </div>
        </div>
        @if (in_array($variant, ['split', 'split_reverse', 'stacked'], true))
            <div class="hero-media">{{ $ctx->picture($section['image'], $variant === 'stacked' ? '100vw' : '(min-width: 900px) 50vw, 100vw', priority: true) }}</div>
        @endif
    </div>
</section>
