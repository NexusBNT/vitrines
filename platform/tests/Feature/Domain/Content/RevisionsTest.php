<?php

namespace Tests\Feature\Domain\Content;

use App\Domain\Content\Revisions;
use App\Domain\Sites\DraftSpecFactory;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_successive_editor_saves_of_the_same_person(): void
    {
        $this->actingAs(User::factory()->create());
        $site = $this->site();

        Revisions::as('editor', fn () => $this->retitle($site, 'Un'));
        Revisions::as('editor', fn () => $this->retitle($site, 'Deux'));

        $this->assertSame(['system', 'editor'], $site->revisions()->orderBy('id')->pluck('source')->all());
        $this->assertSame('Deux', $site->revisions()->latest('id')->first()->spec['pages'][0]['title']);

        $this->travel(11)->minutes();
        Revisions::as('editor', fn () => $this->retitle($site, 'Trois'));

        $this->assertSame(['system', 'editor', 'editor'], $site->revisions()->orderBy('id')->pluck('source')->all());
    }

    public function test_keeps_the_previous_content_of_a_site_without_history(): void
    {
        $site = $this->site();
        $site->revisions()->delete();
        $before = $site->draft_spec;

        Revisions::as('ai', fn () => $this->retitle($site, 'Réécrit'));

        $revisions = $site->revisions()->orderBy('id')->get();
        $this->assertSame(['initial', 'ai'], $revisions->pluck('source')->all());
        $this->assertSame($before, $revisions->first()->spec);
    }

    public function test_ignores_saves_that_do_not_change_the_content(): void
    {
        $site = $this->site();

        $site->update(['draft_spec' => $site->draft_spec, 'settings' => ['foo' => 'bar']]);

        $this->assertSame(1, $site->revisions()->count());
    }

    private function retitle(Site $site, string $title): void
    {
        $spec = $site->draft_spec;
        $spec['pages'][0]['title'] = $title;
        $site->update(['draft_spec' => $spec]);
    }

    private function site(): Site
    {
        $site = Site::factory()->create();
        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site)]);

        return $site->fresh();
    }
}
