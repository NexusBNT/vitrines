<?php

namespace App\Domain\Build;

use App\Enums\SiteStatus;
use App\Models\AuditLog;
use App\Models\Site;

/**
 * Construit la prévisualisation d'un site, servie depuis l'administration.
 */
class BuildPreview
{
    public function __construct(private SiteBuilder $builder) {}

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

    public function url(Site $site): string
    {
        return route('filament.admin.sites.preview', ['site' => $site]);
    }

    private function basePath(Site $site): string
    {
        return parse_url($this->url($site), PHP_URL_PATH).'/';
    }
}
