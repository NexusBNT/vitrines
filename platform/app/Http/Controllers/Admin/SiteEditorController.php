<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\CssScoper;
use App\Domain\Sites\ColorPalette;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Éditeur de pages : page d'accueil de l'application, styles du site pour le canevas, polices.
 */
class SiteEditorController extends Controller
{
    public const CANVAS = '.site-canvas';

    public function show(Site $site): View
    {
        return view('editor', ['site' => $site]);
    }

    /**
     * Feuille de style du thème, limitée au canevas de l'éditeur, avec les couleurs, polices et jetons du site.
     */
    public function theme(Site $site, DraftSpecFactory $drafts): Response
    {
        $design = $site->draft_spec !== null ? Design::forSpec($site->draft_spec) : $drafts->design($site);
        $palette = ColorPalette::from($design['primary'], $design['secondary']);
        $theme = $site->draft_spec['theme']['name'] ?? $site->theme;
        $catalog = Design::fontCatalog();

        $fonts = collect(Design::fonts($design))->map(fn (string $font): string => sprintf(
            '@font-face{font-family:"%s";src:url("%s") format("woff2");font-weight:%s;font-style:normal;font-display:swap}',
            $catalog[$font]['family'],
            route('filament.admin.editor.font', ['font' => $font]),
            $catalog[$font]['weight'],
        ))->implode('');

        $variables = self::CANVAS.'{'.collect($palette)
            ->map(fn (string $color, string $name): string => '--'.str_replace('_', '-', $name).':'.$color)
            ->implode(';').';'.Design::cssVariables($design).'}';

        $css = $fonts
            .CssScoper::scope(File::get(config('vitrines.templates_path')."/themes/{$theme}/theme.css"), self::CANVAS)
            .$variables;

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function font(string $font): BinaryFileResponse
    {
        abort_unless(array_key_exists($font, Design::fontCatalog()), 404);

        return response()->file(config('vitrines.templates_path')."/fonts/{$font}/{$font}.woff2", [
            'Content-Type' => 'font/woff2',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
