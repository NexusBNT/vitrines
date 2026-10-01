<x-filament-widgets::widget>
    @if ($progress)
        @php
            $status = $progress['status'];
            $color = match ($status) { 'done' => 'var(--success-600)', 'failed' => 'var(--danger-600)', default => 'var(--primary-600)' };
            $startedAt = $progress['started_at'] ?? $progress['queued_at'];
        @endphp
        <div
            @if ($isActive) wire:poll.2s @endif
            x-data="{
                start: {{ \Illuminate\Support\Js::from(\Illuminate\Support\Carbon::parse($startedAt)->getTimestampMs()) }},
                end: {{ \Illuminate\Support\Js::from($progress['finished_at'] ? \Illuminate\Support\Carbon::parse($progress['finished_at'])->getTimestampMs() : null) }},
                now: Date.now(),
                get elapsed() {
                    const seconds = Math.max(0, Math.round(((this.end ?? this.now) - this.start) / 1000));
                    return Math.floor(seconds / 60) + ' min ' + String(seconds % 60).padStart(2, '0') + ' s';
                },
            }"
            x-init="setInterval(() => now = Date.now(), 1000)"
            style="position: relative; overflow: hidden; border-radius: 12px; border: 1px solid color-mix(in srgb, {{ $color }} 30%, transparent); background: color-mix(in srgb, {{ $color }} 6%, var(--gray-50, #fff)); padding: 14px 18px 18px;"
        >
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    @if ($isActive)
                        <x-filament::loading-indicator style="width: 20px; height: 20px; color: {{ $color }};" />
                    @elseif ($status === 'done')
                        <x-filament::icon icon="heroicon-o-check-circle" style="width: 22px; height: 22px; color: {{ $color }};" />
                    @else
                        <x-filament::icon icon="heroicon-o-x-circle" style="width: 22px; height: 22px; color: {{ $color }};" />
                    @endif
                    <div>
                        <div style="font-weight: 600;">
                            {{ $progress['label'] }} —
                            @switch($status)
                                @case('queued') en attente de démarrage @break
                                @case('running') étape {{ $progress['current'] + 1 }} / {{ count($progress['steps']) }} : {{ $progress['steps'][$progress['current']] ?? '' }}… @break
                                @case('done') terminée @break
                                @default échec
                            @endswitch
                        </div>
                        <div style="font-size: 0.85rem; opacity: 0.75;">
                            <span x-text="elapsed"></span>
                            @if ($status === 'queued')
                                · la tâche démarre dès qu'un worker de file d'attente est disponible (<code>php artisan queue:work</code>).
                            @elseif ($status === 'running' && $progress['kind'] === 'full')
                                · comptez 3 à 5 minutes, vous pouvez quitter cette page.
                            @elseif ($progress['message'])
                                · {{ \Illuminate\Support\Str::limit($progress['message'], 300) }}
                            @endif
                        </div>
                    </div>
                </div>
                @unless ($isActive)
                    <div style="display: flex; gap: 8px;">
                        @if ($status === 'done')
                            <x-filament::button size="sm" color="gray" tag="a" :href="$previewUrl" target="_blank" icon="heroicon-o-eye">Prévisualiser</x-filament::button>
                            <x-filament::button size="sm" color="gray" tag="a" :href="route('filament.admin.sites.editor', $record)" icon="heroicon-o-document-text">Pages</x-filament::button>
                        @endif
                        <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" wire:click="dismiss" label="Masquer" />
                    </div>
                @endunless
            </div>

            @if ($progress['steps'] !== [])
                <div style="display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; font-size: 0.8rem;">
                    @foreach ($progress['steps'] as $index => $step)
                        @php
                            $state = $status === 'done' || $index < $progress['current'] ? 'done' : ($index === $progress['current'] ? ($status === 'failed' ? 'failed' : 'current') : 'todo');
                        @endphp
                        <span style="padding: 2px 10px; border-radius: 999px; border: 1px solid color-mix(in srgb, {{ $color }} 25%, transparent);
                            {{ $state === 'done' ? 'background: color-mix(in srgb, var(--success-600) 14%, transparent);' : '' }}
                            {{ $state === 'current' ? 'background: color-mix(in srgb, '.$color.' 16%, transparent); font-weight: 600;' : '' }}
                            {{ $state === 'failed' ? 'background: color-mix(in srgb, var(--danger-600) 16%, transparent); font-weight: 600;' : '' }}
                            {{ $state === 'todo' ? 'opacity: 0.55;' : '' }}">
                            {{ $state === 'done' ? '✓ ' : '' }}{{ $step }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div style="position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: color-mix(in srgb, {{ $color }} 15%, transparent);">
                @if ($status === 'queued')
                    <div class="generation-progress-indeterminate" style="height: 100%; width: 30%; background: {{ $color }};"></div>
                @else
                    <div style="height: 100%; width: {{ $status === 'failed' ? 100 : $percent }}%; background: {{ $color }}; transition: width .8s ease;"></div>
                @endif
            </div>
            <style>
                .generation-progress-indeterminate { animation: generation-progress 1.4s ease-in-out infinite; }
                @keyframes generation-progress { from { transform: translateX(-100%); } to { transform: translateX(340%); } }
            </style>
        </div>
    @endif
</x-filament-widgets::widget>
