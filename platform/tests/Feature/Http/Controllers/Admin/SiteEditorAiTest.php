<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Domain\Generation\AiManager;
use App\Domain\Sites\DraftSpecFactory;
use App\Models\AiGeneration;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class SiteEditorAiTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withTwoFactor()->create());
        $this->site = Site::factory()->for(Plan::factory()->pro())->create();
        $this->site->update(['draft_spec' => app(DraftSpecFactory::class)->make($this->site)]);
    }

    public function test_rewrites_a_text(): void
    {
        $provider = $this->fakeAi([['text' => 'Un texte plus court.']]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.transform', $this->site), ['action' => 'shorten', 'text' => 'Un texte bien trop long pour la page.', 'page' => 'home'])
            ->assertOk()
            ->assertJsonPath('text', 'Un texte plus court.');

        $this->assertStringContainsString('Raccourcis ce texte', $provider->requests[0]->prompt);
        $this->assertStringContainsString('Contenu actuel de la page', $provider->requests[0]->prompt);
        $this->assertSame('editor', AiGeneration::sole()->task);
    }

    public function test_retries_then_refuses_an_invented_claim(): void
    {
        $provider = $this->fakeAi([['text' => 'Devis gratuit sous 24 heures.'], ['text' => 'Devis gratuit, garantie décennale.']]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.transform', $this->site), ['action' => 'improve', 'text' => 'Contactez-nous pour un devis.'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'la gratuité'));

        $this->assertCount(2, $provider->requests);
        $this->assertStringContainsString('Une première proposition a été refusée', $provider->requests[1]->prompt);
    }

    public function test_a_claim_already_in_the_original_text_is_accepted(): void
    {
        $this->fakeAi([['text' => 'Votre devis est gratuit.']]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.transform', $this->site), ['action' => 'improve', 'text' => 'Le devis est gratuit.'])
            ->assertOk()
            ->assertJsonPath('text', 'Votre devis est gratuit.');
    }

    public function test_custom_rewrite_requires_an_instruction(): void
    {
        $this->fakeAi([]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.transform', $this->site), ['action' => 'custom', 'text' => 'Texte.'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Précisez la consigne.');
    }

    public function test_writes_blocks_in_the_editor_format(): void
    {
        $this->fakeAi([['blocks' => [
            ['type' => 'heading', 'text' => 'Nos étapes.', 'items' => []],
            ['type' => 'paragraph', 'text' => "Premier paragraphe.\n\nSecond paragraphe.", 'items' => []],
            ['type' => 'bullets', 'text' => '', 'items' => ['Visite', ' ', 'Travaux']],
        ]]]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.write', $this->site), ['instruction' => 'Les étapes', 'page' => 'services'])
            ->assertOk()
            ->assertJsonPath('blocks.0', ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Nos étapes']]])
            ->assertJsonPath('blocks.2.content.0.text', 'Second paragraphe.')
            ->assertJsonCount(2, 'blocks.3.content');
    }

    public function test_suggests_the_title_and_description_of_a_page(): void
    {
        $this->fakeAi([['title' => 'Nos services de plomberie à Rennes', 'meta_description' => str_repeat('Dépannage et installation de plomberie à Rennes. ', 3)]]);

        $this->postJson(route('filament.admin.sites.editor-api.ai.seo', $this->site), ['page' => 'services'])
            ->assertOk()
            ->assertJsonPath('title', 'Nos services de plomberie à Rennes');
    }

    public function test_is_unavailable_without_a_configured_provider(): void
    {
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([], configured: false)]));

        $this->postJson(route('filament.admin.sites.editor-api.ai.transform', $this->site), ['action' => 'fix', 'text' => 'Texte.'])
            ->assertStatus(503);
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     */
    private function fakeAi(array $responses): FakeAiProvider
    {
        $provider = new FakeAiProvider($responses);
        config(['ai.tasks.editor.provider' => 'claude', 'ai.fallback_provider' => 'claude']);
        $this->app->instance(AiManager::class, new AiManager(['claude' => $provider]));

        return $provider;
    }
}
