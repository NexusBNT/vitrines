<?php

namespace Tests\Feature\Jobs;

use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\GenerationProgress;
use App\Enums\SiteStatus;
use App\Jobs\GenerateSiteContent;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeAiProvider;
use Tests\Support\SiteContentFixture;
use Tests\TestCase;

class GenerateSiteContentTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-job-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_saves_the_texts_builds_the_preview_and_notifies_the_user(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([
            SiteContentFixture::valid(['Dépannage plomberie', 'Installation chaudière']),
        ])]));
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $user = User::factory()->create();

        GenerateSiteContent::dispatchSync($site, $user);

        $site->refresh();
        $this->assertSame('ai', $site->draft_spec['generated_by']);
        $this->assertSame(SiteStatus::Preview, $site->status);
        $this->assertSame('Textes rédigés : '.$site->brief['business_name'], $user->notifications()->sole()->data['title']);
    }

    public function test_notifies_the_user_when_the_generation_fails(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([new AiException('Clé API Anthropic refusée.')])]));
        $site = Site::factory()->create();
        $user = User::factory()->create();

        try {
            GenerateSiteContent::dispatchSync($site, $user);
        } catch (AiException) {
        }

        $notification = $user->notifications()->sole()->data;
        $this->assertStringStartsWith('La rédaction a échoué', $notification['title']);
        $this->assertSame('Clé API Anthropic refusée.', $notification['body']);
        $this->assertNull($site->fresh()->draft_spec);
        $this->assertSame('failed', GenerationProgress::get($site)['status']);
        $this->assertSame('Clé API Anthropic refusée.', GenerationProgress::get($site)['message']);
    }
}
