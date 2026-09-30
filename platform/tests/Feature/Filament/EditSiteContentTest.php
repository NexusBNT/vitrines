<?php

namespace Tests\Feature\Filament;

use App\Domain\Generation\AiManager;
use App\Domain\Generation\SiteContent\SiteContentGenerator;
use App\Enums\MediaStatus;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Pages\EditSiteContent;
use App\Jobs\GenerateSiteContent;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeAiProvider;
use Tests\Support\SiteContentFixture;
use Tests\TestCase;

class EditSiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withTwoFactor()->create());
    }

    public function test_saving_edits_keeps_the_hidden_structure_of_each_section(): void
    {
        $site = $this->generatedSite();

        Livewire::test(EditSiteContent::class, ['record' => $site->getRouteKey()])
            ->fillForm($this->changes([
                'draft_spec.pages.0.title' => 'Plombier à Rennes – Dupont',
                'draft_spec.pages.0.sections.0.h1' => 'Un nouveau titre',
                'draft_spec.pages.0.sections.3.paragraphs' => "Premier.\n\nSecond.",
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        $home = $site->fresh()->draft_spec['pages'][0];
        $this->assertSame('Plombier à Rennes – Dupont', $home['title']);
        $this->assertSame('Un nouveau titre', $home['sections'][0]['h1']);
        $this->assertSame('hero', $home['sections'][0]['type']);
        $this->assertSame(['Premier.', 'Second.'], $home['sections'][3]['paragraphs']);
        $this->assertSame('about', $home['sections'][3]['link']['page']);
        $this->assertSame('ai', $site->fresh()->draft_spec['generated_by']);
    }

    public function test_selected_photos_are_stored_as_identifiers(): void
    {
        $site = $this->generatedSite();
        $media = Media::factory()->for($site)->create(['status' => MediaStatus::Ready]);

        Livewire::test(EditSiteContent::class, ['record' => $site->getRouteKey()])
            ->fillForm($this->changes(['draft_spec.pages.0.sections.0.image' => (string) $media->id]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($media->id, $site->fresh()->draft_spec['pages'][0]['sections'][0]['image']);
    }

    public function test_rejects_an_empty_page_title(): void
    {
        $site = $this->generatedSite();

        Livewire::test(EditSiteContent::class, ['record' => $site->getRouteKey()])
            ->fillForm($this->changes(['draft_spec.pages.1.title' => '']))
            ->call('save')
            ->assertHasFormErrors(['draft_spec.pages.1.title' => 'required']);
    }

    public function test_page_explains_what_to_do_when_no_content_exists(): void
    {
        $site = Site::factory()->create();

        $this->get(EditSiteContent::getUrl(['record' => $site]))
            ->assertOk()
            ->assertSee('Aucun contenu pour l');
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

    /**
     * Modifie quelques champs en conservant le reste de l'état du formulaire.
     *
     * @param  array<string, mixed>  $changes
     */
    private function changes(array $changes): Closure
    {
        return function (array $state) use ($changes): array {
            foreach ($changes as $path => $value) {
                data_set($state, $path, $value);
            }

            return $state;
        };
    }

    private function generatedSite(): Site
    {
        $this->app->instance(AiManager::class, new AiManager(['claude' => new FakeAiProvider([
            SiteContentFixture::valid(['Dépannage plomberie', 'Installation chaudière']),
        ])]));
        $site = Site::factory()->for(Plan::factory()->pro())->create();
        $site->update(['draft_spec' => app(SiteContentGenerator::class)->generate($site)['spec']]);

        return $site->fresh();
    }
}
