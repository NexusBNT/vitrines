<section class="section {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container">
        @include('site::themes.artisan.partials.section-heading')
        <ul class="gallery" data-gallery>
            @foreach ($section['images'] as $mediaId)
                @if ($media = $ctx->media($mediaId))
                    <li>
                        <a href="{{ $ctx->largestMediaUrl($media, 'webp') }}" data-caption="{{ $media->caption }}">
                            {{ $ctx->picture($mediaId, '(min-width: 900px) 33vw, (min-width: 600px) 50vw, 100vw') }}
                        </a>
                        @if ($media->caption)
                            <p class="gallery-caption">{{ $media->caption }}</p>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
        @include('site::themes.artisan.partials.section-link')
    </div>
</section>
