<?php

namespace Tests\Feature\Domain\Build;

use App\Domain\Build\BuildTarget;
use App\Domain\Build\SiteBuilder;
use App\Domain\Sites\ApplyDesign;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Domain\Sites\SiteTemplates;
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

    public function test_splits_multi_paragraph_texts_into_paragraphs(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());
        $spec = $site->draft_spec;
        $spec['pages'][4]['sections'][1]['text'] = "Premier paragraphe.\n\nSecond paragraphe.";

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);
        $html = File::get($result->path.'/contact/index.html');

        $this->assertStringContainsString('<p class="section-intro">Premier paragraphe.</p>', $html);
        $this->assertStringContainsString('<p class="section-intro">Second paragraphe.</p>', $html);
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

    public function test_renders_free_blocks_sub_pages_and_their_menu(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create(['max_pages' => 8]));
        $spec = $site->draft_spec;
        $photo = $site->media()->first()->id;
        $spec['pages'][] = [
            'key' => 'p-urgence', 'slug' => 'services/urgence', 'parent' => 'services', 'nav_label' => 'Urgences', 'in_nav' => true,
            'title' => 'Dépannage urgent à Rennes – Dupont', 'meta_description' => 'Fuite, panne de chauffe-eau : comment nous joindre rapidement pour un dépannage de plomberie à Rennes.',
            'noindex' => false,
            'sections' => [
                ['type' => 'page_header', 'h1' => 'Dépannage urgent', 'lead' => null],
                ['type' => 'content', 'blocks' => [
                    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Que faire ?']]],
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Coupez l\'eau <b>vite</b> puis '],
                        ['type' => 'text', 'text' => 'contactez-nous', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'page:contact']]]],
                        ['type' => 'text', 'text' => ' ou lisez la '],
                        ['type' => 'text', 'text' => 'page disparue', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'page:ancienne']]]],
                    ]],
                    ['type' => 'mediaImage', 'attrs' => ['media' => $photo, 'caption' => 'Un chantier', 'size' => 'wide']],
                    ['type' => 'siteButton', 'attrs' => ['label' => 'Nous appeler', 'href' => 'tel:+33612345678', 'variant' => 'primary']],
                ]],
            ],
        ];
        $spec['pages'][2]['in_nav'] = false;

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont.fr'), $spec);
        $page = File::get($result->path.'/services/urgence/index.html');
        $home = File::get($result->path.'/index.html');

        $this->assertSame([], $result->errors(), json_encode($result->errors()));
        $this->assertStringContainsString('<h2>Que faire ?</h2>', $page);
        $this->assertStringContainsString('Coupez l&#039;eau &lt;b&gt;vite&lt;/b&gt; puis <a href="/contact/"><strong>contactez-nous</strong></a> ou lisez la page disparue</p>', $page);
        $this->assertStringContainsString('<figure class="rt-figure rt-figure--wide"><picture>', $page);
        $this->assertStringContainsString('<figcaption>Un chantier</figcaption>', $page);
        $this->assertStringContainsString('<a class="button button-primary" href="tel:+33612345678" data-track="tel">Nous appeler</a>', $page);
        $this->assertStringContainsString('<ul class="sub-nav">', $home);
        $this->assertStringContainsString('<a href="/services/urgence/" >Urgences</a>', preg_replace('/\s+>/', ' >', $home));
        $this->assertStringNotContainsString('<a href="/a-propos/" >', preg_replace('/\s+>/', ' >', $home));
        $this->assertStringContainsString('"position":3,"name":"Urgences"', $page);
        $this->assertStringContainsString('<loc>https://dupont.fr/services/urgence/</loc>', File::get($result->path.'/sitemap.xml'));
    }

    public function test_every_theme_builds_without_quality_errors(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());

        foreach (array_keys(SiteTemplates::ALL) as $template) {
            $design = SiteTemplates::design($template);
            $spec = ApplyDesign::onSpec($site->draft_spec, Design::normalize($design, $design));

            $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont-plomberie.fr'), $spec);
            $html = File::get($result->path.'/index.html');

            $this->assertSame([], $result->errors(), $template.' : '.json_encode($result->errors()));
            $this->assertStringContainsString('nav-'.str_replace('_', '-', $design['nav_layout']), $html, $template);
            $this->assertStringContainsString('class="hero hero--'.str_replace('_', '-', $design['hero_layout']), $html, $template);
            $this->assertSame($design['topbar'] === 'infos' && ! str_starts_with($design['nav_layout'], 'sidebar'), str_contains($html, 'class="topbar"'), $template);
            $this->assertSame(str_starts_with($design['nav_layout'], 'sidebar'), str_contains($html, 'class="header-extra"'), $template);
        }
    }

    public function test_transparent_header_only_sits_on_a_full_width_photo(): void
    {
        $site = $this->siteWithDraft(Plan::factory()->pro()->create());
        $design = SiteTemplates::design('horizon');

        $result = app(SiteBuilder::class)->build($site, BuildTarget::production('https://dupont-plomberie.fr'), ApplyDesign::onSpec($site->draft_spec, Design::normalize($design, $design)));

        $this->assertStringContainsString('class="site-shell header-over"', File::get($result->path.'/index.html'));
        $this->assertStringContainsString('class="site-shell"', File::get($result->path.'/contact/index.html'), 'Les pages sans bandeau photo gardent un en-tête opaque.');
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
