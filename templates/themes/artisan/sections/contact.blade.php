<section class="section" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container contact-grid">
        <div>
            @include('site::themes.artisan.partials.section-heading')
            @if (! empty($section['text']))
                @include('site::themes.artisan.partials.paragraphs', ['text' => $section['text'], 'class' => 'section-intro'])
            @endif
            <form class="contact-form" method="post" action="{{ $ctx->formAction }}" data-contact-form>
                <input type="hidden" name="ts" value="">
                <div class="hp" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="field">
                    <label for="name">Nom</label>
                    <input type="text" id="name" name="name" autocomplete="name" required maxlength="120">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" required maxlength="190">
                    </div>
                    <div class="field">
                        <label for="phone">Téléphone <span class="optional">(facultatif)</span></label>
                        <input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="32">
                    </div>
                </div>
                <div class="field">
                    <label for="message">Votre message</label>
                    <textarea id="message" name="message" rows="6" required maxlength="5000"></textarea>
                </div>
                <div class="field field-check">
                    <input type="checkbox" id="consent" name="consent" value="1" required>
                    <label for="consent">J'accepte que mes données soient utilisées pour répondre à ma demande (<a href="{{ $ctx->url('confidentialite') }}">en savoir plus</a>).</label>
                </div>
                <button class="button button-primary" type="submit">Envoyer</button>
                <p class="form-status" role="status" aria-live="polite"></p>
            </form>
        </div>
        <aside class="contact-card">
            <h2 class="contact-card-title">{{ $site['name'] }}</h2>
            <ul class="contact-list">
                @if ($href = $ctx->phoneHref($site['phone']))
                    <li><span>Téléphone</span><a href="{{ $href }}" data-track="tel">{{ $site['phone'] }}</a></li>
                @endif
                @if ($site['email'])
                    <li><span>Email</span><a href="mailto:{{ $site['email'] }}" data-track="mail">{{ $site['email'] }}</a></li>
                @endif
                @if ($address = $ctx->formattedAddress())
                    <li><span>Adresse</span>{{ $address }}</li>
                @endif
            </ul>
            @if ($hours = $ctx->openingHours())
                <h3>Horaires</h3>
                <dl class="hours">
                    @foreach ($hours as $row)
                        <dt>{{ $row['days'] }}</dt><dd>{{ $row['hours'] }}</dd>
                    @endforeach
                </dl>
                @if ($site['opening_hours_note'])
                    <p class="note">{{ $site['opening_hours_note'] }}</p>
                @endif
            @endif
            @if (($section['show_map'] ?? false) && ($mapUrl = $ctx->mapUrl()))
                <div class="map" data-map-src="{{ $mapUrl }}">
                    <button type="button" class="button button-secondary">Afficher la carte</button>
                    <p class="map-note">La carte est fournie par Google Maps.</p>
                </div>
            @endif
        </aside>
    </div>
</section>
