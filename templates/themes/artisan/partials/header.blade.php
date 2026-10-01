@if ($design['topbar'] === 'infos' && ! $sidebar)
    <div class="topbar">
        <div class="container topbar-inner">
            <p>{{ $site['activity'] }} · {{ $site['city'] }}</p>
            <ul>
                @if ($site['email'])
                    <li><a href="mailto:{{ $site['email'] }}" data-track="mail">{{ $site['email'] }}</a></li>
                @endif
                @if ($href = $ctx->phoneHref($site['phone']))
                    <li><a href="{{ $href }}" data-track="tel">{{ $site['phone'] }}</a></li>
                @endif
            </ul>
        </div>
    </div>
@endif
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ $ctx->url('home') }}">
            <span class="brand-mark" aria-hidden="true">{{ $ctx->initials() }}</span>
            <span class="brand-text">
                <span class="brand-name">{{ $site['name'] }}</span>
                <span class="brand-tagline">{{ $site['activity'] }} · {{ $site['city'] }}</span>
            </span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu">
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="visually-hidden">Menu</span>
        </button>
        <nav class="site-nav" id="menu" aria-label="Menu principal">
            <ul>
                @foreach ($ctx->navigation() as $item)
                    @if ($item['children'] === [])
                        <li><a href="{{ $item['url'] }}" @if ($item['key'] === $page['key']) aria-current="page" @endif>{{ $item['label'] }}</a></li>
                    @else
                        <li class="has-sub">
                            <a href="{{ $item['url'] }}" @if ($item['key'] === $page['key']) aria-current="page" @elseif (collect($item['children'])->contains('key', $page['key'])) aria-current="true" @endif>{{ $item['label'] }}</a>
                            <ul class="sub-nav">
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['url'] }}" @if ($child['key'] === $page['key']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
            @if ($href = $ctx->phoneHref($site['phone']))
                <a class="button button-primary header-call" href="{{ $href }}" data-track="tel">
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>
                    {{ $site['phone'] }}
                </a>
            @endif
            @if ($sidebar)
                <div class="header-extra">
                    @if ($site['email'])
                        <a href="mailto:{{ $site['email'] }}" data-track="mail">{{ $site['email'] }}</a>
                    @endif
                    @if ($address = $ctx->formattedAddress())
                        <p>{{ $address }}</p>
                    @endif
                </div>
            @endif
        </nav>
    </div>
</header>
