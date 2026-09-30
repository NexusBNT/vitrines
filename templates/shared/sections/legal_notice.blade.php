@php($legal = $ctx->legal)
<section class="section">
    <div class="container narrow prose">
        <h2>Éditeur du site</h2>
        <p>
            {{ $legal['company_name'] }}@if ($legal['company_name'] !== $site['name']) ({{ $site['name'] }})@endif<br>
            @if ($legal['address']){{ $legal['address'] }}<br>@endif
            @if ($legal['siret'])SIRET : {{ $legal['siret'] }}<br>@endif
            @if ($site['phone'])Téléphone : {{ $site['phone'] }}<br>@endif
            @if ($site['email'])Email : {{ $site['email'] }}@endif
        </p>
        @if ($legal['publisher'])
            <p>Directeur de la publication : {{ $legal['publisher'] }}</p>
        @endif

        <h2>Conception et maintenance</h2>
        <p>
            @if ($legal['operator']['url'])
                <a href="{{ $legal['operator']['url'] }}" rel="noopener">{{ $legal['operator']['name'] }}</a>
            @else
                {{ $legal['operator']['name'] }}
            @endif
        </p>

        <h2>Hébergement</h2>
        <p>{{ $legal['host']['name'] }}<br>{{ $legal['host']['address'] }}</p>

        <h2>Propriété intellectuelle</h2>
        <p>Les textes, photographies et éléments graphiques de ce site sont la propriété de {{ $legal['company_name'] }} ou de leurs auteurs respectifs. Toute reproduction sans autorisation est interdite.</p>

        @if ($ctx->hasAiIllustrations())
            <h2>Illustrations</h2>
            <p>Certaines illustrations de ce site ont été générées par intelligence artificielle. Elles évoquent les prestations proposées et ne représentent pas des réalisations de {{ $legal['company_name'] }}.</p>

        @endif
        <h2>Données personnelles</h2>
        <p>Le traitement des données transmises via ce site est décrit dans la <a href="{{ $ctx->url('confidentialite') }}">politique de confidentialité</a>.</p>
    </div>
</section>
