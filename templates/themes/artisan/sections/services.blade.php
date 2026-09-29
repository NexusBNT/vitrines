<section class="section {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container">
        @include('site::themes.artisan.partials.section-heading')
        @if (($section['layout'] ?? 'cards') === 'cards')
            <ul class="cards">
                @foreach ($section['items'] as $item)
                    <li class="card">
                        @if ($ctx->media($item['image'] ?? null))
                            <div class="card-media">{{ $ctx->picture($item['image'], '(min-width: 900px) 33vw, 100vw') }}</div>
                        @endif
                        <h3>{{ $item['name'] }}</h3>
                        @if (! empty($item['text']))
                            <p>{{ $item['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            @php($titleTag = empty($section['heading']) ? 'h2' : 'h3')
            <div class="service-list">
                @foreach ($section['items'] as $item)
                    <article class="service" id="{{ \Illuminate\Support\Str::slug($item['name']) }}">
                        @if ($ctx->media($item['image'] ?? null))
                            <div class="service-media">{{ $ctx->picture($item['image'], '(min-width: 900px) 40vw, 100vw') }}</div>
                        @endif
                        <div>
                            <{{ $titleTag }} class="service-title">{{ $item['name'] }}</{{ $titleTag }}>
                            @foreach (preg_split('/\R+/u', (string) ($item['text'] ?? '')) as $paragraph)
                                @if (trim($paragraph) !== '')
                                    <p>{{ trim($paragraph) }}</p>
                                @endif
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        @include('site::themes.artisan.partials.section-link')
    </div>
</section>
