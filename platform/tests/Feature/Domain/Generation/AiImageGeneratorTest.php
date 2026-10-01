<?php

namespace Tests\Feature\Domain\Generation;

use App\Domain\Build\BuildTarget;
use App\Domain\Build\SiteBuilder;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\Images\AiImageGenerator;
use App\Domain\Generation\Providers\OpenAiImageProvider;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Domain\Sites\SiteTemplates;
use App\Enums\MediaCategory;
use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiProvider;
use Tests\Support\FakeImageProvider;
use Tests\TestCase;

class AiImageGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['ai.tasks.image_prompts.provider' => 'openai']);
    }

    public function test_creates_an_illustration_for_each_uncovered_slot(): void
    {
        $images = $this->fakeImages();
        $this->fakePrompts(['hero', 'about', 'service-0', 'service-1']);
        $site = Site::factory()->create();

        $result = app(AiImageGenerator::class)->generate($site, Design::fromStyle('moderne', '#0f766e'));

        $this->assertSame(4, $result['created']);
        $hero = $site->media()->where('ai_slot', 'hero')->sole();
        $this->assertSame(MediaSource::Ai, $hero->source);
        $this->assertSame(MediaCategory::Hero, $hero->category);
        $this->assertSame(MediaStatus::Ready, $hero->status);
        $this->assertSame('Illustration hero', $hero->alt);
        $this->assertStringContainsString('no text, no logos', $images->prompts[0]);
    }

    public function test_illustrations_follow_the_photo_direction_of_the_template(): void
    {
        $images = $this->fakeImages();
        $this->fakePrompts(['hero', 'about', 'service-0', 'service-1']);
        $site = Site::factory()->create();

        app(AiImageGenerator::class)->generate($site, SiteTemplates::design('nocturne'));

        $this->assertStringContainsString('Visual direction: '.SiteTemplates::imageStyle('nocturne'), $images->prompts[0]);
    }

    public function test_client_photos_take_priority_over_illustrations(): void
    {
        $this->fakeImages();
        $site = Site::factory()->create();
        Media::factory()->for($site)->create(['status' => MediaStatus::Ready, 'category' => MediaCategory::Work]);
        Media::factory()->for($site)->create(['status' => MediaStatus::Ready, 'category' => MediaCategory::Team]);

        $this->assertSame(['service-0', 'service-1'], array_keys(app(AiImageGenerator::class)->missingSlots($site)));
    }

    public function test_one_failed_image_does_not_stop_the_others(): void
    {
        $this->fakeImages(failOnCalls: [2]);
        $this->fakePrompts(['hero', 'about', 'service-0', 'service-1']);
        $site = Site::factory()->create();

        $result = app(AiImageGenerator::class)->generate($site, Design::fromStyle('moderne', '#0f766e'));

        $this->assertSame(3, $result['created']);
        $this->assertStringContainsString('filtre de sécurité', $result['warnings'][0]);
    }

    public function test_illustrations_are_placed_in_the_site_but_never_in_the_gallery(): void
    {
        $this->fakeImages();
        $this->fakePrompts(['hero', 'about', 'service-0', 'service-1']);
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        app(AiImageGenerator::class)->generate($site, Design::fromStyle('moderne', '#0f766e'));

        $spec = app(DraftSpecFactory::class)->make($site->fresh());

        $home = $spec['pages'][0]['sections'];
        $this->assertSame($site->media()->where('ai_slot', 'hero')->value('id'), $home[0]['image']);
        $this->assertSame($site->media()->where('ai_slot', 'service-1')->value('id'), $home[1]['items'][1]['image']);
        $this->assertNotContains('gallery', array_column($spec['pages'], 'key'));
    }

    public function test_an_illustration_can_never_be_classified_as_a_real_photo(): void
    {
        $media = Media::factory()->create(['source' => MediaSource::Ai]);

        $media->update(['category' => MediaCategory::Work]);

        $this->assertSame(MediaCategory::Other, $media->fresh()->category);
    }

    public function test_legal_notice_mentions_illustrations_only_when_the_site_uses_them(): void
    {
        config(['vitrines.builds_path' => $buildsPath = sys_get_temp_dir().'/vitrines-img-'.uniqid()]);
        $this->fakeImages();
        $this->fakePrompts(['hero', 'about', 'service-0', 'service-1']);
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $builder = app(SiteBuilder::class);

        $without = $builder->build($site, BuildTarget::production('https://a.fr'), app(DraftSpecFactory::class)->make($site));
        app(AiImageGenerator::class)->generate($site, Design::fromStyle('moderne', '#0f766e'));
        $with = $builder->build($site, BuildTarget::production('https://a.fr'), app(DraftSpecFactory::class)->make($site->fresh()));

        $this->assertStringNotContainsString('générées par intelligence artificielle', File::get($without->path.'/mentions-legales/index.html'));
        $this->assertStringContainsString('générées par intelligence artificielle', File::get($with->path.'/mentions-legales/index.html'));
        $this->assertSame([], $with->errors(), json_encode($with->issues));
        File::deleteDirectory($buildsPath);
    }

    /**
     * @param  list<int>  $failOnCalls
     */
    private function fakeImages(array $failOnCalls = []): FakeImageProvider
    {
        $fake = new FakeImageProvider($failOnCalls);
        $this->app->instance(OpenAiImageProvider::class, $fake);

        return $fake;
    }

    /**
     * @param  list<string>  $slots
     */
    private function fakePrompts(array $slots): void
    {
        $this->app->instance(AiManager::class, new AiManager(['openai' => new FakeAiProvider([[
            'images' => array_map(fn (string $slot): array => ['slot' => $slot, 'prompt' => "Scene for {$slot}.", 'alt' => "Illustration {$slot}"], $slots),
        ]], 'openai')]));
    }
}
