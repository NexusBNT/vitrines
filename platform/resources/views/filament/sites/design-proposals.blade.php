@php
    /** @var list<array<string, mixed>> $proposals */
@endphp
<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
    @foreach ($proposals as $index => $proposal)
        <x-filament::section :compact="true">
            <x-slot name="heading">{{ $proposal['name'] ?? 'Proposition '.($index + 1) }}</x-slot>
            @if ($proposal['is_current'])
                <x-slot name="afterHeader"><x-filament::badge color="success">Design actuel</x-filament::badge></x-slot>
            @endif

            <div style="display:flex;gap:8px;margin-bottom:12px;">
                @foreach ($proposal['swatches'] as $label => $color)
                    <div style="flex:1;">
                        <div style="height:44px;border-radius:8px;border:1px solid rgb(0 0 0 / 10%);background:{{ $color }};"></div>
                        <div style="font-size:11px;opacity:.7;margin-top:4px;">{{ $label }} {{ $color }}</div>
                    </div>
                @endforeach
            </div>

            <p style="font-size:13px;margin:0 0 6px;"><strong>Polices :</strong> {{ $proposal['fonts'] }}</p>
            <p style="font-size:13px;margin:0 0 12px;opacity:.8;">{{ $proposal['summary'] }}</p>
            @if (! empty($proposal['rationale']))
                <p style="font-size:13px;margin:0 0 16px;font-style:italic;">{{ $proposal['rationale'] }}</p>
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <x-filament::button tag="a" :href="$proposal['preview_url']" target="_blank" color="gray" icon="heroicon-o-eye" size="sm">
                    Prévisualiser
                </x-filament::button>
                <x-filament::button
                    wire:click="applyProposal({{ $index }})"
                    wire:confirm="Appliquer ce design au site ? Vos réglages de design actuels seront remplacés."
                    icon="heroicon-o-check"
                    size="sm"
                    :disabled="$proposal['is_current']"
                >
                    Choisir
                </x-filament::button>
            </div>
        </x-filament::section>
    @endforeach
</div>
