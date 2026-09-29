<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SiteStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Preview = 'preview';
    case Live = 'live';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Preview => 'Prévisualisation',
            self::Live => 'En ligne',
            self::Suspended => 'Suspendu',
            self::Archived => 'Archivé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Preview => 'info',
            self::Live => 'success',
            self::Suspended => 'warning',
            self::Archived => 'gray',
        };
    }
}
