<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServerStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Draining = 'draining';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Draining => 'Ne reçoit plus de nouveaux sites',
            self::Disabled => 'Désactivé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draining => 'warning',
            self::Disabled => 'gray',
        };
    }
}
