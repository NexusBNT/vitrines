<?php

namespace Tests\Feature\Domain\Generation;

use App\Domain\Generation\AiManager;
use App\Domain\Generation\Design\DesignProposer;
use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class DesignProposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_normalized_proposals(): void
    {
        $this->fakeAi([
            $this->proposal(['name' => 'Fidèle', 'primary' => '#0F766E']),
            $this->proposal(['name' => 'Chaleureux', 'font_pair' => 'inconnu']),
            $this->proposal(['name' => 'Pastel', 'primary' => '#fde68a']),
        ]);
        $site = Site::factory()->create();

        $proposals = app(DesignProposer::class)->propose($site);

        $this->assertCount(3, $proposals);
        $this->assertSame('#0f766e', $proposals[0]['primary']);
        $this->assertSame('moderne', $proposals[1]['font_pair']);
        $this->assertNotSame('#fde68a', $proposals[2]['primary'], 'Une couleur trop pâle doit être assombrie.');
        $this->assertSame('plain', $proposals[0]['hero_layout'], 'Sans photo, le bandeau reste en texte seul.');
    }

    public function test_sends_the_site_photos_and_uses_the_design_task(): void
    {
        $fake = $this->fakeAi([$this->proposal(), $this->proposal(), $this->proposal()]);
        $site = Site::factory()->create();
        Storage::fake('media')->put('v/480.jpg', 'jpeg-bytes');
        Media::factory()->for($site)->create([
            'status' => MediaStatus::Ready,
            'category' => MediaCategory::Work,
            'variants' => [['width' => 480, 'height' => 360, 'files' => ['jpg' => 'v/480.jpg']]],
        ]);

        $proposals = app(DesignProposer::class)->propose($site, 'Plus haut de gamme');

        $this->assertCount(1, $fake->requests[0]->images);
        $this->assertStringContainsString('Plus haut de gamme', $fake->requests[0]->prompt);
        $this->assertStringContainsString('photos_jointes": "oui', $fake->requests[0]->prompt);
        $this->assertSame('split', $proposals[0]['hero_layout']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function proposal(array $overrides = []): array
    {
        return [
            'name' => 'Direction',
            'rationale' => 'Parce que.',
            'primary' => '#1d4ed8',
            'secondary' => '#0f172a',
            'font_pair' => 'moderne',
            'radius' => 'medium',
            'buttons' => 'pill',
            'shadow' => 'soft',
            'header' => 'light',
            'hero_layout' => 'split',
            'hero_background' => 'gradient',
            'cards' => 'accent',
            'section_alt' => 'tint',
            'footer' => 'dark',
            'headings' => 'normal',
            'density' => 'comfortable',
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $proposals
     */
    private function fakeAi(array $proposals): FakeAiProvider
    {
        $fake = new FakeAiProvider([['proposals' => $proposals]], 'openai');
        config(['ai.tasks.design.provider' => 'openai']);
        $this->app->instance(AiManager::class, new AiManager(['openai' => $fake]));

        return $fake;
    }
}
