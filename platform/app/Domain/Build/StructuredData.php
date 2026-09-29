<?php

namespace App\Domain\Build;

use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Données structurées Schema.org (JSON-LD) d'une page.
 */
class StructuredData
{
    private const DAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    /**
     * @param  array<string, mixed>  $page
     */
    public function forPage(RenderContext $context, array $page): HtmlString
    {
        $graph = [];

        if (in_array($page['key'], ['home', 'contact'], true)) {
            $graph[] = $this->business($context);
        }

        if ($page['key'] !== 'home') {
            $graph[] = $this->breadcrumb($context, $page);
        }

        $faq = collect($page['sections'])->firstWhere('type', 'faq');

        if ($faq !== null && ! empty($faq['items'])) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ], $faq['items']),
            ];
        }

        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );

        return new HtmlString('<script type="application/ld+json">'.$json.'</script>');
    }

    /**
     * @return array<string, mixed>
     */
    private function business(RenderContext $context): array
    {
        $site = $context->site();
        $address = $site['address'];

        $business = [
            '@type' => $site['schema_type'],
            '@id' => $context->absoluteUrl('home').'#business',
            'name' => $site['name'],
            'description' => $site['description'] ?: null,
            'url' => $context->absoluteUrl('home'),
            'telephone' => $site['phone'],
            'email' => $site['email'],
            'areaServed' => array_map(fn (string $town): array => ['@type' => 'City', 'name' => $town], array_values(array_unique([$site['city'], ...$site['service_area']]))),
            'sameAs' => array_values($site['socials']),
        ];

        if (filled($address['street'] ?? null)) {
            $business['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'],
                'postalCode' => $address['postal_code'],
                'addressLocality' => $address['city'],
                'addressCountry' => 'FR',
            ];
        }

        if (! empty($site['opening_hours'])) {
            $business['openingHoursSpecification'] = array_map(fn (array $row): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values(array_map(fn (string $day): string => self::DAYS[$day], array_intersect($row['days'], array_keys(self::DAYS)))),
                'opens' => substr((string) $row['opens'], 0, 5),
                'closes' => substr((string) $row['closes'], 0, 5),
            ], $site['opening_hours']);
        }

        $hero = collect($context->spec['pages'][0]['sections'])->firstWhere('type', 'hero');
        $image = $context->media($hero['image'] ?? null);

        if ($image !== null) {
            $business['image'] = $context->target->origin.'/'.MediaPublisher::publicPath($image, Arr::last($image->variants)['width'], 'jpg');
        }

        return array_filter($business, fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function breadcrumb(RenderContext $context, array $page): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $context->absoluteUrl('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $page['nav_label'], 'item' => $context->absoluteUrl($page['key'])],
            ],
        ];
    }
}
