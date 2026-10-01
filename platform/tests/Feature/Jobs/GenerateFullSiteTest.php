<?php

namespace Tests\Feature\Jobs;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\GenerationProgress;
use App\Domain\Generation\Providers\OpenAiImageProvider;
use App\Domain\Sites\Design;
use App\Enums\MediaSource;
use App\Enums\PlanFeature;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Widgets\GenerationProgressWidget;
use App\Jobs\GenerateFullSite;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeAiProvider;
use Tests\Support\FakeImageProvider;
use Tests\Support\SiteContentFixture;
use Tests\TestCase;

class GenerateFullSiteTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->buildsPath = sys_get_temp_dir().'/vitrines-full-'.uniqid();
        config([
            'vitrines.builds_path' => $this->buildsPath,
            'ai.tasks.design.provider' => 'openai',
            'ai.tasks.image_prompts.provider' => 'openai',
            'ai.tasks.site_content.provider' => 'openai',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_generates_design_illustrations_and_texts_in_one_go(): void
    {
        $this->fakeAi(new FakeImageProvider);
        $site = $this->proPlusSite();
        $user = User::factory()->create();

        GenerateFullSite::dispatchSync($site, $user);

        $site->refresh();
        $this->assertSame('Terre et bois', $site->settings['design']['name']);
        $this->assertSame(4, $site->media()->where('source', MediaSource::Ai)->count());
        $this->assertSame('ai', $site->draft_spec['generated_by']);
        $this->assertNotNull($site->draft_spec['pages'][0]['sections'][0]['image']);
        $this->assertSame('split', $site->draft_spec['pages'][0]['sections'][0]['variant'], 'Le bandeau doit montrer son illustration.');
        $this->assertSame('Site généré : '.$site->brief['business_name'], $user->notifications()->sole()->data['title']);

        $progress = GenerationProgress::get($site);
        $this->assertSame('done', $progress['status']);
        $this->assertSame(['Choix du design', 'Création des illustrations', 'Rédaction des textes', 'Prévisualisation'], $progress['steps']);
    }

    public function test_a_failing_image_generator_still_delivers_the_texts(): void
    {
        $this->fakeAi(new FakeImageProvider(configured: false), withImagePrompts: false);
        $site = $this->proPlusSite();
        $user = User::factory()->create();

        GenerateFullSite::dispatchSync($site, $user);

        $this->assertSame('ai', $site->fresh()->draft_spec['generated_by']);
        $this->assertStringContainsString('Illustrations non générées', $user->notifications()->sole()->data['body']);
    }

    public function test_refuses_sites_whose_plan_does_not_include_it(): void
    {
        $site = Site::factory()->for(Plan::factory()->pro())->create();

        $this->expectException(AiException::class);

        GenerateFullSite::dispatchSync($site);
    }

    public function test_launching_shows_the_progress_and_blocks_a_second_launch(): void
    {
        Queue::fake();
        $this->fakeAi(new FakeImageProvider);
        $this->actingAs(User::factory()->withTwoFactor()->create());
        $site = $this->proPlusSite();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->callAction('generateFullSite')
            ->assertDispatched('generation-queued')
            ->assertActionDisabled('generateFullSite')
            ->assertActionDisabled('generateWithAi');

        $this->assertSame('queued', GenerationProgress::get($site)['status']);

        GenerationProgress::start($site, 'full', GenerateFullSite::LABEL, ['Création des illustrations', 'Rédaction des textes']);
        GenerationProgress::step($site, 'Rédaction des textes');

        Livewire::test(GenerationProgressWidget::class, ['record' => $site])
            ->assertSee('Génération intégrale')
            ->assertSee('étape 2 / 2 : Rédaction des textes…')
            ->assertSeeHtml('wire:poll.2s');

        GenerationProgress::fail($site, 'Clé API refusée.');

        Livewire::test(GenerationProgressWidget::class, ['record' => $site])
            ->assertSee('échec')
            ->assertSee('Clé API refusée.')
            ->assertDontSeeHtml('wire:poll.2s')
            ->call('dismiss');

        $this->assertNull(GenerationProgress::get($site));
    }

    public function test_the_button_is_only_offered_on_plans_that_include_it(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create());

        Livewire::test(EditSite::class, ['record' => Site::factory()->for(Plan::factory()->pro())->create()->getRouteKey()])
            ->assertActionHidden('generateFullSite');
        Livewire::test(EditSite::class, ['record' => $this->proPlusSite()->getRouteKey()])
            ->assertActionVisible('generateFullSite');
    }

    private function proPlusSite(): Site
    {
        $plan = Plan::factory()->pro()->create();
        $plan->update(['features' => [...$plan->features, PlanFeature::AiFull->value]]);

        return Site::factory()->for($plan)->create();
    }

    private function fakeAi(FakeImageProvider $images, bool $withImagePrompts = true): void
    {
        $design = [...Design::fromStyle('moderne', '#9a3412', '#292524'), 'hero_layout' => 'plain', 'name' => 'Terre et bois', 'rationale' => 'Tons chauds.'];

        $this->app->instance(OpenAiImageProvider::class, $images);
        $responses = [['proposals' => [$design, [...$design, 'name' => 'Deux'], [...$design, 'name' => 'Trois']]]];

        if ($withImagePrompts) {
            $responses[] = ['images' => array_map(fn (string $slot): array => ['slot' => $slot, 'prompt' => "Scene {$slot}", 'alt' => "Illustration {$slot}"], ['hero', 'about', 'service-0', 'service-1'])];
        }

        $responses[] = SiteContentFixture::valid(['Dépannage plomberie', 'Installation chaudière']);

        $this->app->instance(AiManager::class, new AiManager(['openai' => new FakeAiProvider($responses, 'openai')]));
    }
}
