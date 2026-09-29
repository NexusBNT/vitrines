<section class="cta-band">
    <div class="container cta-inner">
        <div>
            <h2>{{ $section['heading'] }}</h2>
            @if (! empty($section['text']))
                <p>{{ $section['text'] }}</p>
            @endif
        </div>
        <div class="actions">
            @if ($href = $ctx->phoneHref($site['phone']))
                <a class="button button-light" href="{{ $href }}" data-track="tel">{{ $site['phone'] }}</a>
            @endif
            <a class="button button-outline-light" href="{{ $ctx->contactUrl() }}">Écrire un message</a>
        </div>
    </div>
</section>
