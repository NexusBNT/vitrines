<section class="section {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container narrow">
        @include('site::themes.artisan.partials.section-heading')
        <p>{{ $section['text'] }}</p>
        @if (! empty($section['towns']))
            <ul class="chips">
                @foreach ($section['towns'] as $town)
                    <li>{{ $town }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
