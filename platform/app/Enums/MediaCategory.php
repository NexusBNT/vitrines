<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaCategory: string implements HasLabel
{
    case Hero = 'hero';
    case Work = 'work';
    case Team = 'team';
    case Premises = 'premises';
    case Logo = 'logo';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hero => 'Photo principale',
            self::Work => 'Réalisation',
            self::Team => 'Équipe / portrait',
            self::Premises => 'Locaux / atelier',
            self::Logo => 'Logo',
            self::Other => 'Autre',
        };
    }
}
