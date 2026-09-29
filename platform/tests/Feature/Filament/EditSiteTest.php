<?php

namespace Tests\Feature\Filament;

use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
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
}
