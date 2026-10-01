<?php

namespace Tests\Feature\Domain\Content;

use App\Domain\Content\PagesNormalizer;
use App\Domain\Content\PageTree;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\MediaSource;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PagesNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_keeps_a_generated_site_unchanged_apart_from_defaults(): void
    {
        $site = $this->site();
        $pages = $site->draft_spec['pages'];

        $result = $this->normalize($site, PageTree::toEditor($pages));

        $this->assertSame([], $result['warnings']);
        $this->assertSame(array_column($pages, 'slug'), array_column($result['pages'], 'slug'));
        $this->assertSame($pages[0]['sections'][0]['h1'], $result['pages'][0]['sections'][0]['h1']);
        $this->assertSame('about', $result['pages'][0]['sections'][2]['link']['page']);
    }

    public function test_orders_sub_pages_after_their_parent_and_builds_their_path(): void
    {
        $site = $this->site();
        $pages = PageTree::toEditor($site->draft_spec['pages']);
        $pages[] = $this->page('p-enrobe', 'Enrobé', parent: 'services', segment: 'Enrobé de cour');

        $result = $this->normalize($site, $pages);
        $keys = array_column($result['pages'], 'key');

        $this->assertSame(array_search('services', $keys, true) + 1, array_search('p-enrobe', $keys, true));
        $this->assertSame('services/enrobe-de-cour', collect($result['pages'])->firstWhere('key', 'p-enrobe')['slug']);
    }

    public function test_rejects_structural_problems(): void
    {
        $site = $this->site();
        $pages = PageTree::toEditor($site->draft_spec['pages']);

        $cases = [
            'accueil déplacé' => [...array_slice($pages, 1), $pages[0]],
            'contact supprimé' => array_values(array_filter($pages, fn (array $page): bool => $page['key'] !== 'contact')),
            'adresse réservée' => [...$pages, $this->page('p-x', 'Mentions', segment: 'mentions-legales')],
            'adresse en double' => [...$pages, $this->page('p-x', 'Services bis', segment: 'services')],
            'trop profond' => [...$pages, $this->page('p-a', 'A', parent: 'services'), $this->page('p-b', 'B', parent: 'p-a')],
            'parent inconnu' => [...$pages, $this->page('p-a', 'A', parent: 'nulle-part')],
            'sans en-tête' => [...$pages, [...$this->page('p-a', 'A'), 'sections' => [['type' => 'faq', 'items' => []]]]],
            'deux en-têtes' => [...$pages, [...$this->page('p-a', 'A'), 'sections' => [['type' => 'page_header', 'h1' => 'A'], ['type' => 'hero', 'h1' => 'B']]]],
            'section inconnue' => [...$pages, [...$this->page('p-a', 'A'), 'sections' => [['type' => 'page_header', 'h1' => 'A'], ['type' => 'script']]]],
        ];

        foreach ($cases as $label => $candidate) {
            try {
                $this->normalize($site, $candidate, array_column($pages, 'key'));
                $this->fail("Accepté à tort : {$label}");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('pages', $exception->errors(), $label);
            }
        }
    }

    public function test_enforces_the_page_limit_of_the_offer(): void
    {
        $site = $this->site(Plan::factory()->create(['max_pages' => 1]));
        $home = PageTree::toEditor($site->draft_spec['pages'])[0];

        $this->expectExceptionMessage('une seule page');
        $this->normalize($site, [$home, $this->page('p-a', 'A')], ['home']);
    }

    public function test_rejects_a_gallery_when_the_offer_has_none(): void
    {
        $site = $this->site(Plan::factory()->create(['max_pages' => 1]));
        $home = PageTree::toEditor($site->draft_spec['pages'])[0];
        $home['sections'][] = ['type' => 'gallery', 'images' => []];

        $this->expectExceptionMessage('galerie');
        $this->normalize($site, [$home], ['home']);
    }

    public function test_cleans_contents_and_reports_missing_texts(): void
    {
        $site = $this->site();
        $own = Media::factory()->for($site)->create();
        $illustration = Media::factory()->for($site)->create(['source' => MediaSource::Ai]);
        $foreign = Media::factory()->create();
        $pages = PageTree::toEditor($site->draft_spec['pages']);
        $pages[] = [...$this->page('p-a', 'Garanties'), 'title' => '', 'sections' => [
            ['type' => 'page_header', 'h1' => '  ', 'lead' => str_repeat('a', 400)],
            ['type' => 'gallery', 'heading' => 'Photos', 'images' => [$own->id, $illustration->id, $foreign->id, $own->id]],
            ['type' => 'faq', 'items' => [['question' => 'Q ?', 'answer' => 'R.'], ['question' => '', 'answer' => '']]],
            ['type' => 'services', 'items' => [], 'link' => ['page' => 'supprimee', 'label' => 'Voir']],
            ['type' => 'content', 'blocks' => [['type' => 'paragraph'], ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texte']]], ['type' => 'paragraph']]],
            ['type' => 'content', 'blocks' => [['type' => 'paragraph']]],
        ]];

        $result = $this->normalize($site, $pages);
        $page = collect($result['pages'])->firstWhere('key', 'p-a');

        $this->assertSame('', $page['sections'][0]['h1']);
        $this->assertSame(300, mb_strlen($page['sections'][0]['lead']));
        $this->assertSame([$own->id], $page['sections'][1]['images']);
        $this->assertCount(1, $page['sections'][2]['items']);
        $this->assertNull($page['sections'][3]['link']);
        $this->assertSame([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texte']]]], $page['sections'][4]['blocks']);
        $this->assertCount(5, $page['sections']);
        $this->assertContains('Page « Garanties » : le titre pour Google est vide.', $result['warnings']);
        $this->assertContains('Page « Garanties » : le titre principal (H1) est vide.', $result['warnings']);
    }

    public function test_warns_about_links_to_deleted_pages(): void
    {
        $site = $this->site();
        $pages = PageTree::toEditor($site->draft_spec['pages']);
        $pages[1]['sections'][] = ['type' => 'content', 'blocks' => [['type' => 'siteButton', 'attrs' => ['label' => 'Voir', 'href' => 'page:ancienne']]]];

        $result = $this->normalize($site, $pages);

        $this->assertContains('Page « Services » : un lien vise une page supprimée (ancienne).', $result['warnings']);
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @param  list<string>  $previousKeys
     * @return array{pages: list<array<string, mixed>>, warnings: list<string>}
     */
    private function normalize(Site $site, array $pages, array $previousKeys = ['home', 'contact']): array
    {
        return app(PagesNormalizer::class)->normalize($pages, $site, $previousKeys);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $key, string $label, ?string $parent = null, ?string $segment = null): array
    {
        return [
            'key' => $key,
            'segment' => $segment ?? '',
            'parent' => $parent,
            'nav_label' => $label,
            'in_nav' => true,
            'title' => "{$label} – Dupont",
            'meta_description' => "Tout savoir sur {$label}.",
            'noindex' => false,
            'sections' => [['type' => 'page_header', 'h1' => $label, 'lead' => null]],
        ];
    }

    private function site(?Plan $plan = null): Site
    {
        $site = Site::factory()->for($plan ?? Plan::factory()->pro()->create(['max_pages' => 10]))->create();
        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site)]);

        return $site->fresh();
    }
}
