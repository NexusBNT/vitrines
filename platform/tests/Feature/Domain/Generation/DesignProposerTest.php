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

    public function test_each_proposal_starts_from_a_theme_and_keeps_the_ai_adjustments(): void
    {
        $this->fakeAi([
            $this->proposal(['name' => 'Fidèle', 'template' => 'atelier', 'primary' => '#0F766E']),
            $this->proposal(['name' => 'Nuit', 'template' => 'nocturne', 'nav_layout' => 'sidebar_left', 'services_style' => 'rows', 'primary' => '#16a34a']),
            $this->proposal(['name' => 'Pastel', 'template' => 'inconnu', 'primary' => '#fde68a', 'footer_layout' => 'n\'importe quoi']),
        ]);
        $site = Site::factory()->create();

        $proposals = app(DesignProposer::class)->propose($site);

        $this->assertCount(3, $proposals);
        $this->assertSame('#0f766e', $proposals[0]['primary']);
        $this->assertSame('artisanal', $proposals[0]['font_pair'], 'Les réglages absents viennent du thème.');
        $this->assertSame('infos', $proposals[0]['topbar']);
        $this->assertSame('nocturne', $proposals[1]['template']);
        $this->assertSame('sidebar_left', $proposals[1]['nav_layout'], 'L\'IA peut changer la structure du thème.');
        $this->assertSame('rows', $proposals[1]['services_style']);
        $this->assertSame('dark', $proposals[1]['surface']);
        $this->assertSame('atelier', $proposals[2]['template'], 'Un thème inconnu est remplacé par un thème valide.');
        $this->assertSame('columns', $proposals[2]['footer_layout']);
        $this->assertNotSame('#fde68a', $proposals[2]['primary'], 'Une couleur trop pâle doit être assombrie.');
        $this->assertSame('plain', $proposals[0]['hero_layout'], 'Sans photo, le bandeau reste en texte seul.');
    }

    public function test_the_ai_is_asked_for_every_structure_and_style_setting(): void
    {
        $fake = $this->fakeAi([$this->proposal(), $this->proposal()]);

        app(DesignProposer::class)->propose(Site::factory()->create());

        $required = $fake->requests[0]->schema['properties']['proposals']['items']['required'];
        $this->assertContains('nav_layout', $required);
        $this->assertContains('gallery_style', $required);
        $this->assertContains('surface', $required);
        $this->assertStringContainsString('"nav_layout": "sidebar_left"', $fake->requests[0]->prompt, 'Les réglages des thèmes sont fournis.');
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
        $this->assertSame('split', $proposals[0]['hero_layout'], 'Avec des photos, le bandeau suit la proposition.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function proposal(array $overrides = []): array
    {
        return [
            'template' => 'atelier',
            'name' => 'Direction',
            'rationale' => 'Parce que.',
            'primary' => '#1d4ed8',
            'secondary' => '#0f172a',
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
