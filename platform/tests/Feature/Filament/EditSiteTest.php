<?php

namespace Tests\Feature\Filament;

use App\Domain\Generation\AiManager;
use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Jobs\GenerateSiteContent;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class EditSiteTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-edit-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath]);
        $this->actingAs(User::factory()->withTwoFactor()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_generating_the_draft_fills_the_site_content_from_the_brief(): void
    {
        $site = Site::factory()->create();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->callAction('generateDraft')
            ->assertNotified('Brouillon généré');

        $spec = $site->fresh()->draft_spec;
        $this->assertSame('draft', $spec['generated_by']);
        $this->assertSame($site->brief['business_name'], $spec['site']['name']);
    }

    public function test_preview_is_disabled_until_a_draft_exists(): void
    {
        $site = Site::factory()->create();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->assertActionDisabled('preview');
    }

    public function test_building_the_preview_moves_the_site_to_preview_status(): void
    {
        $site = Site::factory()->create();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->callAction('generateDraft')
            ->callAction('preview')
            ->assertNotified();

        $this->assertSame(SiteStatus::Preview, $site->fresh()->status);
        $this->assertCount(1, File::directories($this->buildsPath.'/'.$site->directoryName()));
    }

    public function test_ai_writing_is_disabled_without_an_api_key(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([], configured: false)]));
        $site = Site::factory()->create();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->assertActionDisabled('generateWithAi');
    }

    public function test_ai_writing_queues_the_generation_with_the_instructions(): void
    {
        Queue::fake();
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([])]));
        $site = Site::factory()->create();

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])
            ->callAction('generateWithAi', ['instructions' => 'Plus chaleureux'])
            ->assertNotified('Rédaction lancée');

        Queue::assertPushed(GenerateSiteContent::class, fn (GenerateSiteContent $job): bool => $job->site->is($site) && $job->instructions === 'Plus chaleureux');
    }

    public function test_the_draft_keeps_the_pages_created_in_the_editor(): void
    {
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])->callAction('generateDraft');

        $spec = $site->fresh()->draft_spec;
        $spec['pages'][] = [
            'key' => 'p-garantie', 'slug' => 'garantie', 'parent' => null, 'nav_label' => 'Garantie', 'in_nav' => true,
            'title' => 'Garantie', 'meta_description' => 'Garantie', 'noindex' => false,
            'sections' => [['type' => 'page_header', 'h1' => 'Garantie', 'lead' => null]],
        ];
        $site->update(['draft_spec' => $spec]);

        Livewire::test(EditSite::class, ['record' => $site->getRouteKey()])->callAction('generateDraft');

        $this->assertContains('p-garantie', array_column($site->fresh()->draft_spec['pages'], 'key'));
        $this->assertSame(['draft', 'system'], $site->revisions()->orderBy('id')->pluck('source')->all());
    }
}
