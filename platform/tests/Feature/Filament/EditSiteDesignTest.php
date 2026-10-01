<?php

namespace Tests\Feature\Filament;

use App\Domain\Build\BuildTarget;
use App\Domain\Build\SiteBuilder;
use App\Domain\Generation\AiManager;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Filament\Resources\Sites\Pages\EditSiteDesign;
use App\Jobs\GenerateDesignProposals;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class EditSiteDesignTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-design-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath, 'ai.tasks.design.provider' => 'openai']);
        $this->user = User::factory()->withTwoFactor()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_job_builds_a_preview_for_each_proposal_and_notifies(): void
    {
        $site = $this->siteWithDraft();
        $this->fakeProposals();

        GenerateDesignProposals::dispatchSync($site, $this->user);

        $this->assertCount(3, $site->fresh()->settings['design_proposals']['items']);
        foreach ([0, 1, 2] as $index) {
            $this->assertFileExists(app(SiteBuilder::class)->designPreviewDirectory($site, $index).'/index.html');
        }
        $this->get(route('filament.admin.sites.design-preview', [$site, 2]))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringStartsWith('Propositions de design prêtes', $this->user->notifications()->sole()->data['title']);
    }

    public function test_choosing_a_proposal_applies_it_to_the_site_and_its_content(): void
    {
        $site = $this->siteWithDraft();
        $this->fakeProposals();
        GenerateDesignProposals::dispatchSync($site, $this->user);

        Livewire::test(EditSiteDesign::class, ['record' => $site->getRouteKey()])
            ->assertSee('Terre et bois')
            ->call('applyProposal', 1)
            ->assertNotified();

        $site->refresh();
        $this->assertSame('Terre et bois', $site->settings['design']['name']);
        $this->assertSame('#9a3412', $site->draft_spec['theme']['colors']['primary']);
        $this->assertSame('artisanal', $site->draft_spec['theme']['design']['font_pair']);
    }

    public function test_manual_settings_are_saved_and_survive_a_new_draft(): void
    {
        $site = $this->siteWithDraft();

        Livewire::test(EditSiteDesign::class, ['record' => $site->getRouteKey()])
            ->fillForm(fn (array $state): array => array_replace_recursive($state, ['settings' => ['design' => ['font_pair' => 'technique', 'header' => 'dark', 'nav_layout' => 'burger', 'gallery_style' => 'mosaic']]]))
            ->call('save')
            ->assertHasNoFormErrors();

        $design = app(DraftSpecFactory::class)->make($site->fresh())['theme']['design'];
        $this->assertSame('technique', $design['font_pair']);
        $this->assertSame('dark', $design['header']);
        $this->assertSame('burger', $design['nav_layout']);
        $this->assertSame('mosaic', $design['gallery_style']);
    }

    public function test_structure_pickers_show_a_thumbnail_for_each_choice(): void
    {
        Livewire::test(EditSiteDesign::class, ['record' => $this->siteWithDraft()->getRouteKey()])
            ->assertSee('Menu latéral à gauche')
            ->assertSee('Photo plein écran, texte encadré')
            ->assertSee('<svg', false);
    }

    public function test_built_site_embeds_and_preloads_only_the_chosen_fonts(): void
    {
        $site = $this->siteWithDraft();
        $spec = $site->draft_spec;
        $spec['theme']['design'] = [...Design::forSpec($spec), 'font_pair' => 'chaleureux'];

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);

        $fonts = array_map('basename', File::files($result->path.'/assets/fonts'));
        $this->assertCount(2, $fonts);
        $this->assertMatchesRegularExpression('#<link rel="preload" href="/assets/fonts/lora\.[a-f0-9]+\.woff2" as="font"#', File::get($result->path.'/index.html'));
        $this->assertSame([], $result->errors());
    }

    public function test_choosing_a_theme_applies_its_structure_and_keeps_the_site_colors_unless_asked(): void
    {
        $site = $this->siteWithDraft();

        Livewire::test(EditSiteDesign::class, ['record' => $site->getRouteKey()])
            ->assertSee('Terroir')
            ->call('applyTemplate', 'terroir');

        $design = $site->fresh()->draft_spec['theme']['design'];
        $this->assertSame('terroir', $design['template']);
        $this->assertSame('cream', $design['surface']);
        $this->assertSame('boxed', $design['page_layout']);
        $this->assertSame('#1d4ed8', $design['primary']);

        Livewire::test(EditSiteDesign::class, ['record' => $site->getRouteKey()])
            ->set('keepColors', false)
            ->call('applyTemplate', 'studio');

        $design = $site->fresh()->draft_spec['theme']['design'];
        $this->assertSame('#18181b', $design['primary']);
        $this->assertSame('sidebar_left', $design['nav_layout']);
    }

    public function test_previewing_a_template_builds_it_without_changing_the_site(): void
    {
        $site = $this->siteWithDraft();
        $before = $site->draft_spec;

        Livewire::test(EditSiteDesign::class, ['record' => $site->getRouteKey()])->call('previewTemplate', 'chantier');

        $this->assertEquals($before, $site->fresh()->draft_spec);
        $this->get(route('filament.admin.sites.design-preview', [$site, 'chantier']))->assertOk();
        $html = File::get(app(SiteBuilder::class)->designPreviewDirectory($site, 'chantier').'/index.html');
        $this->assertStringContainsString('class="topbar"', $html);
        $this->assertStringContainsString('topbar-infos', $html);
    }

    public function test_proposing_designs_is_disabled_without_ai(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['openai' => new FakeAiProvider([], 'openai', configured: false)]));

        Livewire::test(EditSiteDesign::class, ['record' => $this->siteWithDraft()->getRouteKey()])
            ->assertActionDisabled('proposeDesigns');
    }

    private function siteWithDraft(): Site
    {
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site)]);

        return $site->fresh();
    }

    private function fakeProposals(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['openai' => new FakeAiProvider([['proposals' => [
            ['template' => 'horizon', 'name' => 'Fidèle', 'rationale' => 'Reprend la couleur actuelle.', 'primary' => '#1d4ed8', 'secondary' => '#0f172a'],
            ['template' => 'atelier', 'name' => 'Terre et bois', 'rationale' => 'Tons chauds.', 'primary' => '#9a3412', 'secondary' => '#292524'],
            ['template' => 'prestige', 'name' => 'Nuit', 'rationale' => 'Sobre et premium.', 'primary' => '#a16207', 'secondary' => '#1c1917'],
        ]]], 'openai')]));
    }
}
