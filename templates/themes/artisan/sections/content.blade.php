<section class="section section--content" @isset($section['anchor']) id="{{ $section['anchor'] }}" @endisset>
    <div class="container narrow rt">
        {{ $ctx->richText($section['blocks']) }}
    </div>
</section>
