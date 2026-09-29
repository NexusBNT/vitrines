<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MediaStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En traitement',
            self::Ready => 'Prête',
            self::Failed => 'Échec',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
