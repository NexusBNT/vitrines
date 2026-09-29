<?php

namespace App\Domain\Build;

use App\Models\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Données et aides mises à disposition des templates d'un site.
 * Tout le HTML généré ici est échappé ; les templates n'ont jamais besoin de {!! !!} sur du contenu.
 */
class RenderContext
{
    public const DAY_LABELS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<int, Media>  $media  Médias du site, indexés par identifiant
     * @param  array{css: string, js: string, favicon: string}  $assets
     * @param  array<string, mixed>  $legal
     */
    public function __construct(
        public readonly array $spec,
        public readonly BuildTarget $target,
        public readonly array $media,
        public readonly array $assets,
        public readonly array $legal,
        public readonly string $formAction,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function site(): array
    {
        return $this->spec['site'];
    }

    public function isSinglePage(): bool
    {
        return count($this->spec['pages']) === 1;
    }

    /**
     * URL relative d'une page (par clé ou slug), avec ancre facultative.
     */
    public function url(string $pageKey, ?string $anchor = null): string
    {
        $slug = $this->slugFor($pageKey);
        $path = $this->target->basePath.($slug === '' ? '' : $slug.'/');

        return $anchor === null ? $path : $path.'#'.$anchor;
    }

    public function absoluteUrl(string $pageKey): string
    {
        $slug = $this->slugFor($pageKey);

        return $this->target->origin.'/'.($slug === '' ? '' : $slug.'/');
    }

    public function asset(string $name): string
    {
        return $this->target->basePath.$this->assets[$name];
    }

    /**
     * Liens du menu principal : pages en multi-pages, ancres de sections en une page.
     *
     * @return list<array{label: string, url: string, key: string}>
     */
    public function navigation(): array
    {
        if ($this->isSinglePage()) {
            return collect($this->spec['pages'][0]['sections'])
                ->filter(fn (array $section): bool => isset($section['anchor'], $section['nav_label']))
                ->map(fn (array $section): array => [
                    'label' => $section['nav_label'],
                    'url' => $this->url('home', $section['anchor']),
                    'key' => $section['anchor'],
                ])
                ->values()
                ->all();
        }

        return collect($this->spec['pages'])
            ->reject(fn (array $page): bool => $page['key'] === 'home')
            ->map(fn (array $page): array => ['label' => $page['nav_label'], 'url' => $this->url($page['key']), 'key' => $page['key']])
            ->values()
            ->all();
    }

    public function contactUrl(): string
    {
        return $this->isSinglePage() ? $this->url('home', 'contact') : $this->url('contact');
    }

    public function hasPage(string $key): bool
    {
        return collect($this->spec['pages'])->contains('key', $key);
    }

    public function media(?int $id): ?Media
    {
        return $id === null ? null : ($this->media[$id] ?? null);
    }

    /**
     * Balise <picture> AVIF / WebP / JPEG avec srcset, dimensions et chargement différé.
     */
    public function picture(?int $mediaId, string $sizes = '100vw', bool $priority = false, string $class = ''): HtmlString
    {
        $media = $this->media($mediaId);

        if ($media === null || empty($media->variants)) {
            return new HtmlString('');
        }

        $largest = Arr::last($media->variants);
        $sources = '';

        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $type) {
            $sources .= sprintf('<source type="%s" srcset="%s" sizes="%s">', $type, e($this->srcset($media, $format)), e($sizes));
        }

        $img = sprintf(
            '<img src="%s" srcset="%s" sizes="%s" width="%d" height="%d" alt="%s"%s%s>',
            e($this->mediaUrl($media, $largest['width'], 'jpg')),
            e($this->srcset($media, 'jpg')),
            e($sizes),
            $largest['width'],
            $largest['height'],
            e($this->altFor($media)),
            $priority ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"',
            $class !== '' ? ' class="'.e($class).'"' : '',
        );

        return new HtmlString('<picture>'.$sources.$img.'</picture>');
    }

    public function mediaUrl(Media $media, int $width, string $format): string
    {
        return $this->target->basePath.MediaPublisher::publicPath($media, $width, $format);
    }

    public function largestMediaUrl(Media $media, string $format = 'jpg'): string
    {
        return $this->mediaUrl($media, Arr::last($media->variants)['width'], $format);
    }

    public function altFor(Media $media): string
    {
        if (filled($media->alt)) {
            return $media->alt;
        }

        return filled($media->caption) ? $media->caption : $this->site()['activity'].' – '.$this->site()['name'];
    }

    /**
     * Lien tel: au format international (+33…) à partir d'un numéro saisi librement.
     */
    public function phoneHref(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if (preg_match('/^0(\d{9})$/', $digits, $matches)) {
            $digits = '+33'.$matches[1];
        }

        return 'tel:'.$digits;
    }

    /**
     * Horaires regroupés pour l'affichage : « Lundi – Vendredi » => « 08:00 – 18:00 ».
     *
     * @return list<array{days: string, hours: string}>
     */
    public function openingHours(): array
    {
        return collect($this->site()['opening_hours'])
            ->map(fn (array $row): array => [
                'days' => $this->describeDays($row['days'] ?? []),
                'hours' => substr((string) $row['opens'], 0, 5).' – '.substr((string) $row['closes'], 0, 5),
            ])
            ->all();
    }

    public function formattedAddress(): ?string
    {
        $address = $this->site()['address'];

        if (blank($address['street'] ?? null)) {
            return null;
        }

        return trim($address['street'].', '.trim(($address['postal_code'] ?? '').' '.($address['city'] ?? '')), ', ');
    }

    public function mapUrl(): ?string
    {
        $address = $this->formattedAddress();

        return $address === null ? null : 'https://www.google.com/maps?output=embed&q='.rawurlencode($this->site()['name'].', '.$address);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr(Str::ascii($this->site()['name']), 0, 1));
    }

    private function slugFor(string $pageKey): string
    {
        $page = collect($this->spec['pages'])->firstWhere('key', $pageKey);

        return $page['slug'] ?? $pageKey;
    }

    private function srcset(Media $media, string $format): string
    {
        return collect($media->variants)
            ->map(fn (array $variant): string => $this->mediaUrl($media, $variant['width'], $format).' '.$variant['width'].'w')
            ->implode(', ');
    }

    /**
     * @param  list<string>  $days
     */
    private function describeDays(array $days): string
    {
        $order = array_keys(self::DAY_LABELS);
        $indexes = collect($days)->map(fn (string $day): int|false => array_search($day, $order, true))->filter(fn ($index): bool => $index !== false)->sort()->values();

        $isContiguous = $indexes->count() > 2 && $indexes->last() - $indexes->first() === $indexes->count() - 1;

        if ($isContiguous) {
            return self::DAY_LABELS[$order[$indexes->first()]].' – '.self::DAY_LABELS[$order[$indexes->last()]];
        }

        return $indexes->map(fn (int $index): string => self::DAY_LABELS[$order[$index]])->implode(', ');
    }
}
