<?php

namespace App\Domain\Build;

use App\Domain\Sites\ColorPalette;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Génère le site statique complet (HTML, CSS, JS, images, sitemap, robots) d'une spécification.
 */
class SiteBuilder
{
    private const COMPRESSIBLE = ['html', 'css', 'js', 'xml', 'svg', 'txt'];

    public function __construct(
        private MediaPublisher $mediaPublisher,
        private StructuredData $structuredData,
        private QualityChecker $qualityChecker,
    ) {}

    /**
     * @param  array<string, mixed>|null  $spec  Spécification à construire (par défaut : le brouillon du site)
     */
    public function build(Site $site, BuildTarget $target, ?array $spec = null): BuildResult
    {
        $spec ??= $site->draft_spec ?? throw new RuntimeException('Le site n\'a pas encore de contenu à construire.');
        $theme = $spec['theme']['name'];

        if (! array_key_exists($theme, config('vitrines.themes'))) {
            throw new RuntimeException("Thème inconnu : {$theme}");
        }

        $buildId = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $siteDirectory = $this->siteBuildsDirectory($site);
        $finalDirectory = $siteDirectory.'/'.$buildId;
        $workDirectory = $finalDirectory.'.tmp';

        File::ensureDirectoryExists($workDirectory);

        try {
            $media = $this->mediaPublisher->publish($site, $spec, $workDirectory);
            $palette = ColorPalette::from($spec['theme']['colors']['primary'], $spec['theme']['colors']['secondary'] ?? null);
            $assets = $this->writeAssets($workDirectory, $theme, $palette, $spec);

            $context = new RenderContext(
                spec: $spec,
                target: $target,
                media: $media,
                assets: $assets,
                legal: $this->legal($site),
                formAction: rtrim(config('vitrines.forms_endpoint'), '/').'/f/'.$site->public_key,
            );

            $pages = [...$spec['pages'], ...$this->utilityPages($spec)];

            foreach ($pages as $page) {
                $html = view("site::themes.{$theme}.layout", [
                    'ctx' => $context,
                    'page' => $page,
                    'palette' => $palette,
                    'ogImage' => $this->ogImage($context, $page),
                    'structuredData' => $this->structuredData->forPage($context, $page),
                ])->render();

                $file = $page['key'] === 'not-found' ? '404.html' : ($page['slug'] === '' ? '' : $page['slug'].'/').'index.html';
                File::ensureDirectoryExists(dirname($workDirectory.'/'.$file));
                File::put($workDirectory.'/'.$file, $this->tidy($html));
            }

            File::put($workDirectory.'/sitemap.xml', $this->sitemap($context, $spec['pages']));
            File::put($workDirectory.'/robots.txt', $this->robots($target));

            $issues = $this->qualityChecker->check($workDirectory, $spec, $target);
            $this->precompress($workDirectory);

            File::moveDirectory($workDirectory, $finalDirectory);
        } catch (\Throwable $exception) {
            File::deleteDirectory($workDirectory);

            throw $exception;
        }

        if (! $target->production) {
            $this->prunePreviewBuilds($siteDirectory);
        }

        return new BuildResult($buildId, $finalDirectory, $issues, count($pages));
    }

    public function siteBuildsDirectory(Site $site): string
    {
        return config('vitrines.builds_path').'/'.$site->directoryName();
    }

    public function latestBuildDirectory(Site $site): ?string
    {
        $directories = File::directories($this->siteBuildsDirectory($site));
        $directories = array_values(array_filter($directories, fn (string $directory): bool => ! str_ends_with($directory, '.tmp')));
        sort($directories);

        return $directories === [] ? null : end($directories);
    }

    /**
     * @param  array<string, string>  $palette
     * @param  array<string, mixed>  $spec
     * @return array{css: string, js: string, favicon: string}
     */
    private function writeAssets(string $directory, string $theme, array $palette, array $spec): array
    {
        $templates = config('vitrines.templates_path');

        $variables = ':root{'.collect($palette)
            ->map(fn (string $color, string $name): string => '--'.str_replace('_', '-', $name).':'.$color)
            ->implode(';').'}';

        $files = [
            'css' => ['css', $variables."\n".$this->minifyCss(File::get("{$templates}/themes/{$theme}/theme.css"))],
            'js' => ['js', File::get("{$templates}/js/site.js")],
            'favicon' => ['svg', $this->favicon($spec['site']['name'], $palette)],
        ];

        $assets = [];

        foreach ($files as $name => [$extension, $content]) {
            $path = sprintf('assets/%s.%s.%s', $name === 'favicon' ? 'favicon' : 'site', substr(hash('sha256', $content), 0, 10), $extension);
            File::ensureDirectoryExists($directory.'/assets');
            File::put($directory.'/'.$path, $content);
            $assets[$name] = $path;
        }

        return $assets;
    }

    /**
     * @param  array<string, string>  $palette
     */
    private function favicon(string $name, array $palette): string
    {
        $letter = e(Str::upper(Str::substr(Str::ascii($name), 0, 1)));

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="14" fill="'.$palette['primary'].'"/>'
            .'<text x="32" y="44" font-family="system-ui,sans-serif" font-size="36" font-weight="800" text-anchor="middle" fill="'.$palette['primary_ink'].'">'.$letter.'</text>'
            .'</svg>';
    }

    /**
     * Pages ajoutées automatiquement : mentions légales, confidentialité, remerciement, 404.
     *
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function utilityPages(array $spec): array
    {
        $name = $spec['site']['name'];

        $page = fn (string $key, string $title, string $description, string $section, bool $noindex = false): array => [
            'key' => $key,
            'slug' => $key,
            'nav_label' => $title,
            'title' => $title.' – '.$name,
            'meta_description' => $description,
            'noindex' => $noindex,
            'sections' => [
                ['type' => 'page_header', 'h1' => $title, 'lead' => null],
                ['type' => $section],
            ],
        ];

        return [
            $page('mentions-legales', 'Mentions légales', "Mentions légales du site de {$name} : éditeur, hébergeur et propriété intellectuelle.", 'legal_notice'),
            $page('confidentialite', 'Politique de confidentialité', "Comment {$name} traite les données transmises via ce site : formulaire de contact, mesure d'audience, vos droits.", 'privacy'),
            $page('merci', 'Message envoyé', "Votre message a bien été transmis à {$name}. Merci pour votre confiance, nous revenons vers vous rapidement.", 'thanks', true),
            $page('not-found', 'Page introuvable', "Cette page n'existe pas ou a été déplacée. Retrouvez toutes les informations de {$name} depuis l'accueil du site.", 'not_found', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legal(Site $site): array
    {
        $client = $site->client;

        return [
            'company_name' => $client->company_name,
            'siret' => $client->siret,
            'address' => trim(implode(', ', array_filter([$client->address_line, trim($client->postal_code.' '.$client->city)]))) ?: null,
            'publisher' => $client->contact_name,
            'operator' => config('vitrines.operator'),
            'host' => config('vitrines.host'),
            'retention_months' => 12,
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function ogImage(RenderContext $context, array $page): ?string
    {
        foreach ([$page, $context->spec['pages'][0]] as $candidate) {
            foreach ($candidate['sections'] as $section) {
                $media = $context->media($section['image'] ?? ($section['images'][0] ?? null));

                if ($media !== null) {
                    $variant = collect($media->variants)->last(fn (array $variant): bool => $variant['width'] <= 1200) ?? $media->variants[0];

                    return $context->target->origin.'/'.MediaPublisher::publicPath($media, $variant['width'], 'jpg');
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private function sitemap(RenderContext $context, array $pages): string
    {
        $today = now()->toDateString();
        $urls = collect($pages)
            ->map(fn (array $page): string => '<url><loc>'.e($context->absoluteUrl($page['key'])).'</loc><lastmod>'.$today.'</lastmod></url>')
            ->implode("\n");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$urls."\n".'</urlset>'."\n";
    }

    private function robots(BuildTarget $target): string
    {
        if (! $target->indexable()) {
            return "User-agent: *\nDisallow: /\n";
        }

        return "User-agent: *\nAllow: /\n\nSitemap: {$target->origin}/sitemap.xml\n";
    }

    private function minifyCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;

        return trim(preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css);
    }

    private function tidy(string $html): string
    {
        return preg_replace("/\n\s*\n+/", "\n", $html) ?? $html;
    }

    private function precompress(string $directory): void
    {
        foreach (File::allFiles($directory) as $file) {
            if (in_array($file->getExtension(), self::COMPRESSIBLE, true)) {
                File::put($file->getPathname().'.gz', gzencode(File::get($file->getPathname()), 9));
            }
        }
    }

    private function prunePreviewBuilds(string $siteDirectory): void
    {
        $directories = File::directories($siteDirectory);
        sort($directories);

        foreach (array_slice($directories, 0, -config('vitrines.keep_preview_builds')) as $directory) {
            File::deleteDirectory($directory);
        }
    }
}
