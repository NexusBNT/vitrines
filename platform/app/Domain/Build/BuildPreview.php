<?php

namespace App\Domain\Build;

use App\Domain\Sites\ApplyDesign;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\SiteStatus;
use App\Models\AuditLog;
use App\Models\Site;

/**
 * Construit la prévisualisation d'un site, servie depuis l'administration.
 */
class BuildPreview
{
    public function __construct(private SiteBuilder $builder, private DraftSpecFactory $drafts) {}

    public function handle(Site $site): BuildResult
    {
        $target = BuildTarget::preview(config('app.url'), $this->basePath($site));
        $result = $this->builder->build($site, $target);

        if ($site->status === SiteStatus::Draft) {
            $site->update(['status' => SiteStatus::Preview]);
        }

        AuditLog::record('preview_built', $site, ['build' => $result->buildId, 'errors' => count($result->errors())]);

        return $result;
    }

    /**
     * Construit l'aperçu d'une proposition de design sans modifier le site.
     *
     * @param  array<string, mixed>  $design
     */
    public function designProposal(Site $site, int $proposal, array $design): BuildResult
    {
        $spec = ApplyDesign::onSpec($site->draft_spec ?? $this->drafts->make($site), $design);
        $target = BuildTarget::preview(config('app.url'), parse_url($this->designUrl($site, $proposal), PHP_URL_PATH).'/');

        return $this->builder->build($site, $target, $spec, $this->builder->designPreviewDirectory($site, $proposal));
    }

    public function designUrl(Site $site, int $proposal): string
    {
        return route('filament.admin.sites.design-preview', ['site' => $site, 'proposal' => $proposal]);
    }

    public function url(Site $site): string
    {
        return route('filament.admin.sites.preview', ['site' => $site]);
    }

    private function basePath(Site $site): string
    {
        return parse_url($this->url($site), PHP_URL_PATH).'/';
    }
}
