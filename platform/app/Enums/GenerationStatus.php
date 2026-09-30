<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GenerationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En cours',
            self::Succeeded => 'Réussie',
            self::Failed => 'Échec',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Succeeded => 'success',
            self::Failed => 'danger',
        };
    }
}
