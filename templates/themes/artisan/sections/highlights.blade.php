<section class="section section-highlights {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container">
        @include('site::themes.artisan.partials.section-heading')
        <ul class="highlights">
            @foreach ($section['items'] as $item)
                <li>
                    <h3>{{ $item['title'] }}</h3>
                    @if (! empty($item['text']))
                        <p>{{ $item['text'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
