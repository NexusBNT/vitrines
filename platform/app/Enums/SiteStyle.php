<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SiteStyle: string implements HasLabel
{
    case Sober = 'sobre';
    case Warm = 'chaleureux';
    case Modern = 'moderne';
    case Premium = 'premium';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sober => 'Sobre et professionnel',
            self::Warm => 'Chaleureux et artisanal',
            self::Modern => 'Moderne et épuré',
            self::Premium => 'Haut de gamme',
        };
    }
}
