<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanFeature: string implements HasLabel
{
    case Gallery = 'gallery';
    case LocalSeo = 'local_seo';
    case Stats = 'stats';
    case SearchConsole = 'search_console';
    case Posts = 'posts';
    case SeoReports = 'seo_reports';
    case PrioritySupport = 'priority_support';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gallery => 'Galerie / réalisations',
            self::LocalSeo => 'SEO local',
            self::Stats => 'Statistiques',
            self::SearchConsole => 'Google Search Console',
            self::Posts => 'Actualités',
            self::SeoReports => 'Suivi SEO et rapports',
            self::PrioritySupport => 'Modifications prioritaires',
        };
    }
}
