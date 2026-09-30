@foreach (preg_split('/\R{2,}/u', trim((string) $text)) ?: [] as $paragraph)
    @if (trim($paragraph) !== '')
        <p{!! isset($class) ? ' class="'.e($class).'"' : '' !!}>{{ trim($paragraph) }}</p>
    @endif
@endforeach
