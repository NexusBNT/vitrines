<?php

namespace App\Domain\Build;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;

/**
 * Contrôle automatique d'un build avant publication.
 * Les erreurs bloquent la publication ; les avertissements sont affichés à l'éditeur.
 */
class QualityChecker
{
    /** @var list<array{level: string, page: string, message: string}> */
    private array $issues = [];

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array{level: string, page: string, message: string}>
     */
    public function check(string $directory, array $spec, BuildTarget $target): array
    {
        $this->issues = [];
        $titles = [];

        $this->checkSpec($spec);

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }

            $page = '/'.str_replace('index.html', '', $file->getRelativePathname());
            $document = HTMLDocument::createFromString(File::get($file->getPathname()), LIBXML_NOERROR);

            $title = trim($document->querySelector('title')?->textContent ?? '');
            $titles[$title][] = $page;

            $this->checkHeadings($document, $page);
            $this->checkMeta($document, $page, $title);
            $this->checkImages($document, $page);
            $this->checkLinks($document, $page, $directory, $target);
        }

        foreach ($titles as $title => $pages) {
            if ($title !== '' && count($pages) > 1) {
                $this->add('error', implode(', ', $pages), "Titre identique sur plusieurs pages : « {$title} ».");
            }
        }

        return $this->issues;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function checkSpec(array $spec): void
    {
        $pageKeys = array_column($spec['pages'], 'key');

        foreach ($spec['pages'] as $page) {
            $path = '/'.($page['slug'] === '' ? '' : $page['slug'].'/');

            foreach ($page['sections'] as $section) {
                $isEmpty = match ($section['type']) {
                    'services', 'faq', 'highlights' => empty($section['items']),
                    'gallery' => empty($section['images']),
                    'about' => empty($section['paragraphs']),
                    default => false,
                };

                if ($isEmpty) {
                    $this->add('warning', $path, "La section « {$section['type']} » est vide.");
                }

                if (isset($section['link']['page']) && ! in_array($section['link']['page'], $pageKeys, true)) {
                    $this->add('warning', $path, "Le lien « {$section['link']['label']} » vise une page absente du site.");
                }
            }
        }
    }

    private function checkHeadings(HTMLDocument $document, string $page): void
    {
        $count = $document->querySelectorAll('h1')->length;

        if ($count !== 1) {
            $this->add('error', $page, "La page doit avoir exactement un titre H1 (trouvé : {$count}).");
        }
    }

    private function checkMeta(HTMLDocument $document, string $page, string $title): void
    {
        if ($title === '') {
            $this->add('error', $page, 'Balise <title> vide.');
        } elseif (mb_strlen($title) > 65) {
            $this->add('warning', $page, 'Titre trop long ('.mb_strlen($title).' caractères, 65 maximum conseillés).');
        }

        $description = trim($document->querySelector('meta[name="description"]')?->getAttribute('content') ?? '');
        $length = mb_strlen($description);

        if ($length === 0) {
            $this->add('error', $page, 'Meta description absente.');
        } elseif ($length < 50 || $length > 160) {
            $this->add('warning', $page, "Meta description de {$length} caractères (50 à 160 conseillés).");
        }
    }

    private function checkImages(HTMLDocument $document, string $page): void
    {
        foreach ($document->querySelectorAll('img') as $image) {
            if (! $image->hasAttribute('alt')) {
                $this->add('error', $page, 'Image sans attribut alt : '.$image->getAttribute('src'));
            }
        }
    }

    private function checkLinks(HTMLDocument $document, string $page, string $directory, BuildTarget $target): void
    {
        foreach ($document->querySelectorAll('a[href]') as $link) {
            $href = $link->getAttribute('href');

            if (str_starts_with($href, 'tel:')) {
                if (! preg_match('/^tel:\+?\d{6,15}$/', $href)) {
                    $this->add('error', $page, "Numéro de téléphone cliquable invalide : {$href}");
                }

                continue;
            }

            if (! str_starts_with($href, $target->basePath) || str_starts_with($href, '//')) {
                continue;
            }

            $path = substr(strtok($href, '#?') ?: '', strlen($target->basePath));
            $file = $path === '' || str_ends_with($path, '/') ? $path.'index.html' : $path;

            if (! File::exists($directory.'/'.$file)) {
                $this->add('error', $page, "Lien interne cassé : {$href}");
            }
        }
    }

    private function add(string $level, string $page, string $message): void
    {
        $this->issues[] = ['level' => $level, 'page' => $page, 'message' => $message];
    }
}
