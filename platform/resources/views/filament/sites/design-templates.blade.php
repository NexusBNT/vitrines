@php
    /** @var list<array<string, mixed>> $templates */
@endphp
@include('filament.sites.design-styles')
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <p style="margin:0;font-size:14px;opacity:.8;">Chaque thème a sa propre structure. Choisir un thème remplace la structure et le style ; vous pourrez tout ajuster ensuite.</p>
    <label style="display:flex;align-items:center;gap:8px;font-size:14px;">
        <x-filament::input.checkbox wire:model.live="keepColors" />
        Garder mes couleurs
    </label>
</div>
<div class="dz-themes">
    @foreach ($templates as $template)
        <article class="dz-theme {{ $template['is_current'] ? 'is-current' : '' }}">
            <div class="dz-theme-thumb">{!! $template['svg'] !!}</div>
            <div class="dz-theme-body">
                <div class="dz-theme-title">
                    <span>{{ $template['label'] }}</span>
                    @if ($template['is_current'])
                        <x-filament::badge color="success" size="sm">Actuel</x-filament::badge>
                    @endif
                </div>
                <p class="dz-theme-text">{{ $template['description'] }}</p>
                <p class="dz-theme-meta">Idéal pour : {{ $template['ideal_for'] }}</p>
                <p class="dz-theme-meta">Inspiré de {{ $template['inspiration'] }} · {{ $template['fonts'] }}</p>
                <div class="dz-theme-actions">
                    <x-filament::button wire:click="previewTemplate('{{ $template['key'] }}')" color="gray" icon="heroicon-o-eye" size="sm">
                        Aperçu
                    </x-filament::button>
                    <x-filament::button
                        wire:click="applyTemplate('{{ $template['key'] }}')"
                        wire:confirm="Appliquer le thème {{ $template['label'] }} ? La structure et le style actuels seront remplacés."
                        icon="heroicon-o-check"
                        size="sm"
                    >
                        Choisir
                    </x-filament::button>
                </div>
            </div>
        </article>
    @endforeach
</div>
