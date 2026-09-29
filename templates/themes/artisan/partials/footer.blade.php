<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <p class="footer-name">{{ $site['name'] }}</p>
            <p>{{ $site['activity'] }} à {{ $site['city'] }}</p>
            @if (! empty($site['socials']))
                <ul class="socials">
                    @foreach ($site['socials'] as $network => $url)
                        <li><a href="{{ $url }}" rel="noopener" target="_blank">{{ ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'google_business' => 'Google'][$network] ?? ucfirst($network) }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <p class="footer-title">Contact</p>
            <ul class="footer-list">
                @if ($href = $ctx->phoneHref($site['phone']))
                    <li><a href="{{ $href }}" data-track="tel">{{ $site['phone'] }}</a></li>
                @endif
                @if ($site['email'])
                    <li><a href="mailto:{{ $site['email'] }}" data-track="mail">{{ $site['email'] }}</a></li>
                @endif
                @if ($address = $ctx->formattedAddress())
                    <li>{{ $address }}</li>
                @endif
            </ul>
        </div>
        @if ($hours = $ctx->openingHours())
            <div>
                <p class="footer-title">Horaires</p>
                <ul class="footer-list">
                    @foreach ($hours as $row)
                        <li>{{ $row['days'] }} : {{ $row['hours'] }}</li>
                    @endforeach
                    @if ($site['opening_hours_note'])
                        <li>{{ $site['opening_hours_note'] }}</li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
    <div class="container footer-bottom">
        <p>© {{ date('Y') }} {{ $site['name'] }}</p>
        <ul>
            <li><a href="{{ $ctx->url('mentions-legales') }}">Mentions légales</a></li>
            <li><a href="{{ $ctx->url('confidentialite') }}">Confidentialité</a></li>
        </ul>
    </div>
</footer>
