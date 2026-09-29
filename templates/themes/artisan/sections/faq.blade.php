<section class="section {{ $index % 2 === 0 ? 'section--alt' : '' }}" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container narrow">
        @include('site::themes.artisan.partials.section-heading')
        <div class="faq">
            @foreach ($section['items'] as $item)
                <details>
                    <summary>{{ $item['question'] }}</summary>
                    <p>{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
