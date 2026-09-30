<?php

namespace Tests\Feature\Domain\Generation;

use App\Domain\Build\BuildTarget;
use App\Domain\Build\SiteBuilder;
use App\Domain\Generation\AiException;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\SiteContent\SiteContentGenerator;
use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeAiProvider;
use Tests\Support\SiteContentFixture;
use Tests\TestCase;

class SiteContentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private const SERVICES = ['Dépannage plomberie', 'Installation chaudière'];

    public function test_places_generated_texts_into_the_site_structure(): void
    {
        $this->fakeAi([SiteContentFixture::valid(self::SERVICES)]);
        $site = Site::factory()->for(Plan::factory()->pro())->create();

        $spec = app(SiteContentGenerator::class)->generate($site)['spec'];

        $home = $spec['pages'][0];
        $this->assertSame('ai', $spec['generated_by']);
        $this->assertSame('Plombier chauffagiste à Rennes – Dupont Plomberie', $home['title']);
        $this->assertSame('Votre plombier chauffagiste à Rennes', $home['sections'][0]['h1']);
        $this->assertSame('Résumé : Dépannage plomberie.', $home['sections'][1]['items'][0]['text']);
        $this->assertSame('Dépannage plomberie', $home['sections'][1]['items'][0]['name']);
        $this->assertSame(['hero', 'services', 'highlights', 'about', 'zone', 'faq', 'cta'], array_column($home['sections'], 'type'));
        $this->assertStringStartsWith('Détail complet', $spec['pages'][1]['sections'][1]['items'][0]['text']);
        $this->assertSame(['Demander la date de création de l\'entreprise.'], $spec['suggestions']);
    }

    public function test_generated_site_builds_without_quality_errors(): void
    {
        $this->fakeAi([SiteContentFixture::valid(self::SERVICES)]);
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $spec = app(SiteContentGenerator::class)->generate($site)['spec'];
        config(['vitrines.builds_path' => $buildsPath = sys_get_temp_dir().'/vitrines-ai-'.uniqid()]);

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);

        $this->assertSame([], $result->errors(), json_encode($result->issues));
        $this->assertStringContainsString('"@type":"FAQPage"', File::get($result->path.'/index.html'));
        File::deleteDirectory($buildsPath);
    }

    public function test_single_page_site_gets_faq_before_contact(): void
    {
        $this->fakeAi([SiteContentFixture::valid(self::SERVICES)]);
        $site = Site::factory()->for(Plan::factory()->create(['max_pages' => 1]))->create();

        $spec = app(SiteContentGenerator::class)->generate($site)['spec'];

        $this->assertCount(1, $spec['pages']);
        $this->assertSame(['hero', 'services', 'highlights', 'about', 'zone', 'faq', 'contact'], array_column($spec['pages'][0]['sections'], 'type'));
        $this->assertSame(['Premier paragraphe.', 'Second paragraphe.'], $spec['pages'][0]['sections'][3]['paragraphs']);
    }

    public function test_retries_with_corrections_when_the_text_invents_a_certification(): void
    {
        $invented = SiteContentFixture::valid(self::SERVICES);
        $invented['home']['lead'] = 'Artisan certifié RGE depuis 2005.';
        $fake = $this->fakeAi([$invented, SiteContentFixture::valid(self::SERVICES)]);
        $site = Site::factory()->create();

        $spec = app(SiteContentGenerator::class)->generate($site)['spec'];

        $this->assertCount(2, $fake->requests);
        $this->assertStringContainsString('certification', $fake->requests[1]->prompt);
        $this->assertStringContainsString('une année', $fake->requests[1]->prompt);
        $this->assertSame('Dépannage, installation et rénovation pour les particuliers.', $spec['pages'][0]['sections'][0]['lead']);
    }

    public function test_keeps_a_certification_that_the_brief_mentions(): void
    {
        $content = SiteContentFixture::valid(self::SERVICES);
        $content['home']['lead'] = 'Artisan certifié RGE.';
        $fake = $this->fakeAi([$content]);
        $site = Site::factory()->create();
        $site->update(['brief' => [...$site->brief, 'description' => 'Entreprise certifiée RGE.']]);

        app(SiteContentGenerator::class)->generate($site->fresh());

        $this->assertCount(1, $fake->requests);
    }

    public function test_fails_after_two_invalid_proposals(): void
    {
        $invalid = SiteContentFixture::valid(['Un seul service']);
        $this->fakeAi([$invalid, $invalid]);
        $site = Site::factory()->create();

        try {
            app(SiteContentGenerator::class)->generate($site);
            $this->fail('La génération aurait dû échouer.');
        } catch (AiException $exception) {
            $this->assertStringContainsString('exactement 2 services', $exception->getMessage());
        }

        $this->assertSame(2, AiGeneration::where('status', GenerationStatus::Succeeded)->count());
    }

    public function test_sends_the_brief_facts_and_extra_instructions_to_the_model(): void
    {
        $fake = $this->fakeAi([SiteContentFixture::valid(self::SERVICES)]);
        $site = Site::factory()->create();

        app(SiteContentGenerator::class)->generate($site, 'Ton plus chaleureux.');

        $request = $fake->requests[0];
        $this->assertStringContainsString('Dépannage plomberie', $request->prompt);
        $this->assertStringContainsString('Ton plus chaleureux.', $request->prompt);
        $this->assertStringContainsString('Exactitude', $request->system);
        $this->assertFalse($request->schema['additionalProperties']);
    }

    /**
     * @param  list<array<string, mixed>|AiException>  $responses
     */
    private function fakeAi(array $responses): FakeAiProvider
    {
        $fake = new FakeAiProvider($responses);
        $this->app->instance(AiManager::class, new AiManager(['claude' => $fake]));

        return $fake;
    }
}
