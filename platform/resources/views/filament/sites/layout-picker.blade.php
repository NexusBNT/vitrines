@php
    /** @var array<string, array{label: string, svg: string}> $options */
@endphp
@include('filament.sites.design-styles')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="dz-grid" role="radiogroup">
        @foreach ($options as $value => $option)
            <label class="dz-option">
                <input type="radio" name="{{ $getId() }}" value="{{ $value }}" wire:model.live="{{ $getStatePath() }}">
                <span class="dz-thumb">{!! $option['svg'] !!}</span>
                <span class="dz-label">{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
