<?php

namespace App\Domain\Sites;

use App\Domain\Content\Revisions;
use App\Models\AuditLog;
use App\Models\Site;

/**
 * Retient un design pour le site : il est enregistré dans les réglages (et survit donc à une
 * nouvelle rédaction) et appliqué au contenu actuel.
 */
class ApplyDesign
{
    /**
     * @param  array<string, mixed>  $design
     */
    public function handle(Site $site, array $design): void
    {
        $settings = $site->settings ?? [];
        $settings['design'] = $design;

        Revisions::as('design', fn () => $site->update([
            'settings' => $settings,
            'draft_spec' => $site->draft_spec === null ? null : self::onSpec($site->draft_spec, $design),
        ]));

        AuditLog::record('design_applied', $site, ['name' => $design['name'] ?? null]);
    }

    /**
     * Spécification avec le design donné (couleurs, jetons, mise en page du bandeau d'accueil).
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public static function onSpec(array $spec, array $design): array
    {
        $spec['theme']['design'] = $design;
        $spec['theme']['colors'] = ['primary' => $design['primary'], 'secondary' => $design['secondary']];

        foreach ($spec['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if ($section['type'] === 'hero' && ($section['image'] ?? null) !== null) {
                    $spec['pages'][$pageIndex]['sections'][$sectionIndex]['variant'] = $design['hero_layout'];
                }
            }
        }

        return $spec;
    }
}
