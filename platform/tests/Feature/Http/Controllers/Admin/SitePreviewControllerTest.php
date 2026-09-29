<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Domain\Build\BuildPreview;
use App\Domain\Sites\DraftSpecFactory;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SitePreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-preview-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath]);

        $this->site = Site::factory()->create();
        $this->site->update(['draft_spec' => app(DraftSpecFactory::class)->make($this->site)]);
        app(BuildPreview::class)->handle($this->site);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_guest_cannot_see_a_preview(): void
    {
        $this->get(route('filament.admin.sites.preview', $this->site))->assertRedirect('/admin/login');
    }

    public function test_serves_the_home_page_as_non_indexable_html(): void
    {
        $response = $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('filament.admin.sites.preview', $this->site));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('<h1>', $response->getFile()->getContent());
    }

    public function test_serves_a_sub_page_and_its_stylesheet(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create());
        $home = $this->get(route('filament.admin.sites.preview', $this->site))->getFile()->getContent();
        preg_match('#href="(/admin/sites/\d+/preview/assets/site\.[a-f0-9]+\.css)"#', $home, $css);

        $this->get(route('filament.admin.sites.preview', [$this->site, 'mentions-legales/']))->assertOk();
        $this->get($css[1])->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    public function test_refuses_to_serve_files_outside_the_build(): void
    {
        File::put($this->buildsPath.'/secret.txt', 'secret');

        $response = $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get('/admin/sites/'.$this->site->id.'/preview/..%2F..%2Fsecret.txt');

        $response->assertNotFound();
        $this->assertStringNotContainsString('secret', $response->getFile()->getContent());
    }
}
