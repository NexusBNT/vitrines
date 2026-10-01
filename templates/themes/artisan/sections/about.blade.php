@php($image = $ctx->media($section['image'] ?? null))
<section class="section section-about {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container {{ $image ? 'split' : 'narrow' }}">
        <div class="prose">
            @include('site::themes.artisan.partials.section-heading')
            @foreach ($section['paragraphs'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @include('site::themes.artisan.partials.section-link')
        </div>
        @if ($image)
            <div class="split-media">{{ $ctx->picture($section['image'], '(min-width: 900px) 50vw, 100vw') }}</div>
        @endif
    </div>
</section>
