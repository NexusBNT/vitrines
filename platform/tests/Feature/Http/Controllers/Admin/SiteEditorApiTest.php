<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Domain\Content\PageTree;
use App\Domain\Sites\DraftSpecFactory;
use App\Http\Controllers\Admin\SiteEditorApiController;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SiteEditorApiTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-editor-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_guests_cannot_use_the_editor(): void
    {
        $site = $this->site();

        $this->get(route('filament.admin.sites.editor', $site))->assertRedirect();
        $this->getJson(route('filament.admin.sites.editor-api.bootstrap', $site))->assertUnauthorized();
    }

    public function test_opens_the_editor_page(): void
    {
        $this->withoutVite();
        $site = $this->site();

        $this->actingAs($this->admin())
            ->get(route('filament.admin.sites.editor', $site))
            ->assertOk()
            ->assertSee('editor-root', false)
            ->assertSee(route('filament.admin.sites.editor-api.bootstrap', $site), false);
    }

    public function test_bootstrap_describes_the_site_and_its_pages(): void
    {
        $site = $this->site();

        $this->actingAs($this->admin())
            ->getJson(route('filament.admin.sites.editor-api.bootstrap', $site))
            ->assertOk()
            ->assertJsonPath('site.max_pages', 8)
            ->assertJsonPath('site.gallery', true)
            ->assertJsonPath('content.pages.0.key', 'home')
            ->assertJsonPath('content.pages.1.segment', 'services')
            ->assertJsonPath('content.version', SiteEditorApiController::version($site))
            ->assertJsonPath('protected_pages', ['home', 'contact']);
    }

    public function test_bootstrap_of_a_site_without_content_offers_a_draft(): void
    {
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $this->actingAs($this->admin());

        $this->getJson(route('filament.admin.sites.editor-api.bootstrap', $site))->assertJsonPath('content', null);
        $this->postJson(route('filament.admin.sites.editor-api.draft', $site))->assertOk()->assertJsonPath('content.pages.0.key', 'home');
        $this->assertSame('draft', $site->revisions()->sole()->source);
    }

    public function test_saves_pages_and_returns_the_new_version(): void
    {
        $site = $this->site();
        $pages = PageTree::toEditor($site->draft_spec['pages']);
        $pages[0]['sections'][0]['h1'] = 'Nouveau titre';
        $pages[] = [
            'key' => 'p-garantie', 'segment' => '', 'parent' => 'services', 'nav_label' => 'Garantie', 'in_nav' => true,
            'title' => 'Garantie – Dupont', 'meta_description' => '', 'noindex' => false,
            'sections' => [
                ['type' => 'page_header', 'h1' => 'Notre garantie', 'lead' => null],
                ['type' => 'content', 'blocks' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bonjour '], ['type' => 'text', 'text' => 'à tous', 'marks' => [['type' => 'bold']]]]]]],
            ],
        ];

        $response = $this->actingAs($this->admin())
            ->putJson(route('filament.admin.sites.editor-api.save', $site), ['version' => SiteEditorApiController::version($site), 'pages' => $pages])
            ->assertOk()
            ->assertJsonPath('warnings', ['Page « Garantie » : la meta description est vide.']);

        $site->refresh();
        $garantie = collect($site->draft_spec['pages'])->firstWhere('key', 'p-garantie');
        $this->assertSame(SiteEditorApiController::version($site), $response->json('version'));
        $this->assertSame('Nouveau titre', $site->draft_spec['pages'][0]['sections'][0]['h1']);
        $this->assertSame('services/garantie', $garantie['slug']);
        $this->assertSame('Bonjour ', $garantie['sections'][1]['blocks'][0]['content'][0]['text']);
        $this->assertSame(['system', 'editor'], $site->revisions()->orderBy('id')->pluck('source')->all());
    }

    public function test_refuses_to_overwrite_a_newer_version(): void
    {
        $site = $this->site();
        $staleVersion = SiteEditorApiController::version($site);
        $spec = $site->draft_spec;
        $spec['pages'][0]['title'] = 'Modifié ailleurs';
        $site->update(['draft_spec' => $spec]);

        $this->actingAs($this->admin())
            ->putJson(route('filament.admin.sites.editor-api.save', $site), ['version' => $staleVersion, 'pages' => PageTree::toEditor($spec['pages'])])
            ->assertConflict()
            ->assertJsonPath('conflict', true);
    }

    public function test_reports_structural_errors(): void
    {
        $site = $this->site();
        $pages = array_values(array_filter(PageTree::toEditor($site->draft_spec['pages']), fn (array $page): bool => $page['key'] !== 'contact'));

        $this->actingAs($this->admin())
            ->putJson(route('filament.admin.sites.editor-api.save', $site), ['version' => SiteEditorApiController::version($site), 'pages' => $pages])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pages' => 'La page Contact ne peut pas être supprimée.']);
    }

    public function test_lists_compares_and_restores_revisions(): void
    {
        $site = $this->site();
        $this->actingAs($this->admin());
        $original = $site->draft_spec;
        $pages = PageTree::toEditor($original['pages']);
        $pages[0]['sections'][0]['h1'] = 'Titre modifié';
        $this->putJson(route('filament.admin.sites.editor-api.save', $site), ['version' => SiteEditorApiController::version($site), 'pages' => $pages])->assertOk();

        $revisions = $this->getJson(route('filament.admin.sites.editor-api.revisions', $site))
            ->assertOk()
            ->assertJsonPath('revisions.0.label', 'Modification dans l\'éditeur')
            ->json('revisions');
        $initial = SiteRevision::find(end($revisions)['id']);

        $this->getJson(route('filament.admin.sites.editor-api.revision', [$site, $initial]))
            ->assertOk()
            ->assertJsonPath('current.0.key', 'home')
            ->assertJsonPath('pages.0.text', fn (string $text): bool => str_starts_with($text, $original['pages'][0]['sections'][0]['h1']));

        $this->postJson(route('filament.admin.sites.editor-api.restore', [$site, $initial]))
            ->assertOk()
            ->assertJsonPath('content.pages.0.sections.0.h1', $original['pages'][0]['sections'][0]['h1']);

        $this->assertSame('restore', $site->revisions()->latest('id')->first()->source);
    }

    public function test_a_revision_of_another_site_is_not_reachable(): void
    {
        $site = $this->site();
        $other = $this->site();

        $this->actingAs($this->admin())
            ->getJson(route('filament.admin.sites.editor-api.revision', [$site, $other->revisions()->first()]))
            ->assertNotFound();
    }

    public function test_builds_the_preview(): void
    {
        $site = $this->site();

        $this->actingAs($this->admin())
            ->postJson(route('filament.admin.sites.editor-api.preview', $site))
            ->assertOk()
            ->assertJsonPath('url', route('filament.admin.sites.preview', $site));
    }

    public function test_theme_stylesheet_is_scoped_to_the_canvas(): void
    {
        $site = $this->site();

        $this->actingAs($this->admin())
            ->get(route('filament.admin.sites.editor.theme', $site))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertSee('.site-canvas .hero', false)
            ->assertSee('.site-canvas{--primary:', false)
            ->assertDontSee('body{', false);
    }

    private function admin(): User
    {
        return User::factory()->withTwoFactor()->create();
    }

    private function site(): Site
    {
        $site = Site::factory()->for(Plan::factory()->pro()->create(['max_pages' => 8]))->create();
        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site)]);

        return $site->fresh();
    }
}
