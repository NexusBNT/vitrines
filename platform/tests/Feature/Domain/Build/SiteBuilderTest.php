<?php

namespace Tests\Feature\Domain\Build;

use App\Domain\Build\BuildTarget;
use App\Domain\Build\SiteBuilder;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $buildsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildsPath = sys_get_temp_dir().'/vitrines-builds-'.uniqid();
        config(['vitrines.builds_path' => $this->buildsPath, 'vitrines.forms_endpoint' => 'https://api.test']);
        Storage::fake('media');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildsPath);

        parent::tearDown();
    }

    public function test_builds_every_page_of_a_pro_site_without_quality_errors(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont-plomberie.fr'));

        $this->assertSame([], $result->issues, json_encode($result->issues));
        $this->assertStringContainsString('href="/a-propos/"', File::get($result->path.'/index.html'));
        $this->assertStringContainsString('href="/realisations/"', File::get($result->path.'/index.html'));
        foreach (['index.html', 'services/index.html', 'a-propos/index.html', 'realisations/index.html', 'contact/index.html', 'mentions-legales/index.html', 'confidentialite/index.html', 'merci/index.html', '404.html', 'sitemap.xml', 'robots.txt'] as $file) {
            $this->assertFileExists($result->path.'/'.$file);
        }
        $this->assertFileExists($result->path.'/index.html.gz');
    }

    public function test_home_page_carries_local_seo_markup(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont-plomberie.fr'));
        $html = File::get($result->path.'/index.html');

        $this->assertStringContainsString('<h1>Plombier chauffagiste à Rennes</h1>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://dupont-plomberie.fr/">', $html);
        $this->assertStringContainsString('"@type":"Plumber"', $html);
        $this->assertStringContainsString('"dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday"]', $html);
        $this->assertStringContainsString('href="tel:+33612345678"', $html);
        $this->assertStringContainsString('action="https://api.test/f/'.$site->public_key.'"', File::get($result->path.'/contact/index.html'));
        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_preview_build_is_not_indexable(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->create());

        $result = app(SiteBuilder::class)->build($site, BuildTarget::preview('https://preview.test', '/apercu/'));

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', File::get($result->path.'/index.html'));
        $this->assertSame("User-agent: *\nDisallow: /\n", File::get($result->path.'/robots.txt'));
        $this->assertStringContainsString('href="/apercu/mentions-legales/"', File::get($result->path.'/index.html'));
    }

    public function test_single_page_offer_produces_one_page_with_anchor_navigation(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->create(['max_pages' => 1]));

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'));
        $html = File::get($result->path.'/index.html');

        $this->assertDirectoryDoesNotExist($result->path.'/services');
        $this->assertStringContainsString('href="/#contact"', $html);
        $this->assertSame([], $result->errors(), json_encode($result->issues));
    }

    public function test_escapes_html_typed_in_the_brief(): void
    {
        $site = Site::factory()->create();
        $site->update(['brief' => [...$site->brief, 'business_name' => '<script>alert(1)</script>']]);
        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site->fresh())]);

        $result = app(SiteBuilder::class)->build($site->fresh(), BuildTarget::production('https://dupont.fr'));

        $this->assertStringNotContainsString('<script>alert(1)</script>', File::get($result->path.'/index.html'));
    }

    public function test_ignores_photos_belonging_to_another_site(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());
        $foreign = Media::factory()->create(['status' => MediaStatus::Ready, 'variants' => [['width' => 480, 'height' => 360, 'files' => ['jpg' => 'x.jpg']]]]);
        $spec = $site->draft_spec;
        $spec['pages'][0]['sections'][0]['image'] = $foreign->id;

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);

        $this->assertDirectoryDoesNotExist($result->path.'/media/'.$foreign->id);
    }

    public function test_reports_a_duplicated_title_as_an_error(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());
        $spec = $site->draft_spec;
        $spec['pages'][1]['title'] = $spec['pages'][0]['title'];

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Titre identique', $result->errors()[0]['message']);
    }

    private function siteWithDraft(Plan $plan): Site
    {
        $site = Site::factory()->for($plan)->create();
        $site->update(['brief' => [
            ...$site->brief,
            'business_name' => 'Dupont Plomberie',
            'activity' => 'Plombier chauffagiste',
            'description' => "Artisan plombier depuis 2009.\nNous intervenons chez les particuliers.",
            'phone' => '06 12 34 56 78',
            'city' => 'Rennes',
        ]]);

        foreach ([MediaCategory::Hero, MediaCategory::Work] as $category) {
            $media = Media::factory()->for($site)->create(['category' => $category, 'status' => MediaStatus::Ready]);
            $files = [];
            foreach (['avif', 'webp', 'jpg'] as $format) {
                Storage::disk('media')->put("variants/{$media->id}/480.{$format}", 'img');
                $files[$format] = "variants/{$media->id}/480.{$format}";
            }
            $media->update(['variants' => [['width' => 480, 'height' => 360, 'files' => $files]]]);
        }

        $site->update(['draft_spec' => app(DraftSpecFactory::class)->make($site->fresh())]);

        return $site->fresh();
    }
}
