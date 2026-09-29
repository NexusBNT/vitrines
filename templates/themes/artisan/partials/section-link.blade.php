@if (! empty($section['link']) && $ctx->hasPage($section['link']['page']))
    <p class="section-link"><a class="button button-secondary" href="{{ $ctx->url($section['link']['page']) }}">{{ $section['link']['label'] }}</a></p>
@endif
