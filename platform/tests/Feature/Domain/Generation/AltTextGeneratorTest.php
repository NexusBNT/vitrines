<?php

namespace Tests\Feature\Domain\Generation;

use App\Domain\Generation\AiManager;
use App\Domain\Generation\AltText\AltTextGenerator;
use App\Domain\Media\StoreUploadedMedia;
use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Jobs\DescribeMedia;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class AltTextGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_the_empty_alt_text_category_and_caption_from_the_image(): void
    {
        $fake = $this->fakeAi(['alt' => 'Salle de bain rénovée avec douche à l\'italienne', 'category' => 'work', 'caption' => 'Rénovation à Rennes']);
        $media = $this->readyMedia();

        app(AltTextGenerator::class)->describe($media);

        $media->refresh();
        $this->assertSame('Salle de bain rénovée avec douche à l\'italienne', $media->alt);
        $this->assertSame(MediaCategory::Work, $media->category);
        $this->assertSame('Rénovation à Rennes', $media->caption);
        $this->assertSame('image/jpeg', $fake->requests[0]->images[0]['media_type']);
        $this->assertSame(base64_encode('jpeg-bytes'), $fake->requests[0]->images[0]['data']);
    }

    public function test_never_replaces_what_the_team_already_typed(): void
    {
        $this->fakeAi(['alt' => 'Proposition IA', 'category' => 'work', 'caption' => 'Légende IA']);
        $media = $this->readyMedia(['alt' => 'Texte saisi', 'category' => MediaCategory::Hero, 'caption' => 'Légende saisie']);

        app(AltTextGenerator::class)->describe($media);

        $media->refresh();
        $this->assertSame('Texte saisi', $media->alt);
        $this->assertSame(MediaCategory::Hero, $media->category);
        $this->assertSame('Légende saisie', $media->caption);
    }

    public function test_processing_a_photo_queues_its_description_when_ai_is_available(): void
    {
        Queue::fake([DescribeMedia::class]);
        $this->fakeAi([]);
        Storage::fake('media')->put('incoming/a.jpg', $this->jpeg());

        app(StoreUploadedMedia::class)->handle(Site::factory()->create(), 'incoming/a.jpg', 'a.jpg');

        Queue::assertPushed(DescribeMedia::class);
    }

    public function test_processing_a_photo_skips_the_description_without_ai(): void
    {
        Queue::fake([DescribeMedia::class]);
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([], configured: false)]));
        Storage::fake('media')->put('incoming/a.jpg', $this->jpeg());

        app(StoreUploadedMedia::class)->handle(Site::factory()->create(), 'incoming/a.jpg', 'a.jpg');

        Queue::assertNotPushed(DescribeMedia::class);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function fakeAi(array $response): FakeAiProvider
    {
        $fake = new FakeAiProvider($response === [] ? [] : [$response]);
        $this->app->instance(AiManager::class, new AiManager(['claude' => $fake]));

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function readyMedia(array $attributes = []): Media
    {
        Storage::fake('media')->put('variants/x/480.jpg', 'jpeg-bytes');

        return Media::factory()->create([
            'status' => MediaStatus::Ready,
            'variants' => [['width' => 480, 'height' => 360, 'files' => ['jpg' => 'variants/x/480.jpg']]],
            ...$attributes,
        ]);
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(320, 240);
        ob_start();
        imagejpeg($image);

        return ob_get_clean();
    }
}
