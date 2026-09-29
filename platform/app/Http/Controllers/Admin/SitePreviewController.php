<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Build\SiteBuilder;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SitePreviewController extends Controller
{
    private const CONTENT_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'jpg' => 'image/jpeg',
    ];

    /**
     * Sert le dernier build de prévisualisation d'un site, pour l'administration uniquement.
     */
    public function __invoke(Site $site, SiteBuilder $builder, string $path = ''): BinaryFileResponse
    {
        $root = $builder->latestBuildDirectory($site);
        abort_if($root === null, 404, 'Aucune prévisualisation : cliquez sur « Prévisualiser ».');

        $file = realpath($root.'/'.$path);

        if ($file !== false && is_dir($file)) {
            $file = realpath($file.'/index.html');
        }

        $isInsideBuild = $file !== false && str_starts_with($file, realpath($root).DIRECTORY_SEPARATOR) && is_file($file);
        $extension = $isInsideBuild ? pathinfo($file, PATHINFO_EXTENSION) : null;

        if (! $isInsideBuild || ! isset(self::CONTENT_TYPES[$extension])) {
            return response()->file($root.'/404.html', ['Content-Type' => self::CONTENT_TYPES['html']])->setStatusCode(404);
        }

        return response()->file($file, [
            'Content-Type' => self::CONTENT_TYPES[$extension],
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store',
        ]);
    }
}
