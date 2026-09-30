<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MediaSource: string implements HasColor, HasLabel
{
    case Upload = 'upload';
    case Ai = 'ai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upload => 'Photo du client',
            self::Ai => 'Illustration IA',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Upload => 'gray',
            self::Ai => 'warning',
        };
    }
}
