@php
    /** @var list<array<string, mixed>> $proposals */
@endphp
@include('filament.sites.design-styles')
<div class="dz-themes">
    @foreach ($proposals as $index => $proposal)
        <article class="dz-theme {{ $proposal['is_current'] ? 'is-current' : '' }}">
            <div class="dz-theme-thumb">{!! $proposal['svg'] !!}</div>
            <div class="dz-theme-body">
                <div class="dz-theme-title">
                    <span>{{ $proposal['name'] ?? 'Proposition '.($index + 1) }}</span>
                    @if ($proposal['is_current'])
                        <x-filament::badge color="success" size="sm">Actuel</x-filament::badge>
                    @endif
                </div>
                <p class="dz-theme-meta">{{ $proposal['summary'] }} · {{ $proposal['fonts'] }}</p>
                @if (! empty($proposal['rationale']))
                    <p class="dz-theme-text" style="font-style:italic;">{{ $proposal['rationale'] }}</p>
                @endif
                <div class="dz-theme-actions">
                    <x-filament::button tag="a" :href="$proposal['preview_url']" target="_blank" color="gray" icon="heroicon-o-eye" size="sm">
                        Aperçu
                    </x-filament::button>
                    <x-filament::button
                        wire:click="applyProposal({{ $index }})"
                        wire:confirm="Appliquer cette proposition ? La structure et le style actuels seront remplacés."
                        icon="heroicon-o-check"
                        size="sm"
                        :disabled="$proposal['is_current']"
                    >
                        Choisir
                    </x-filament::button>
                </div>
            </div>
        </article>
    @endforeach
</div>
