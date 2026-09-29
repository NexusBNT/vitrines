<section class="page-header">
    <div class="container">
        <h1>{{ $section['h1'] }}</h1>
        @if (! empty($section['lead']))
            <p class="lead">{{ $section['lead'] }}</p>
        @endif
    </div>
</section>
