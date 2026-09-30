<?php

namespace App\Filament\Resources\Sites\Pages\Concerns;

use App\Domain\Build\BuildPreview;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Construit la prévisualisation du site et affiche le résultat du contrôle qualité.
 *
 * @property Site $record
 */
trait BuildsPreview
{
    protected function buildPreview(BuildPreview $preview): void
    {
        try {
            $result = $preview->handle($this->record->refresh());
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('La prévisualisation a échoué')->body($exception->getMessage())->danger()->persistent()->send();

            return;
        }

        $issues = collect($result->issues)
            ->map(fn (array $issue): string => ($issue['level'] === 'error' ? '⛔ ' : '⚠️ ').e($issue['page'].' — '.$issue['message']))
            ->implode('<br>');

        Notification::make()
            ->title($result->hasErrors() ? 'Prévisualisation prête, avec des erreurs à corriger' : 'Prévisualisation prête')
            ->body($issues === '' ? 'Contrôle qualité : aucun problème détecté.' : new HtmlString($issues))
            ->status($result->hasErrors() ? 'warning' : 'success')
            ->persistent()
            ->actions([
                Action::make('open')->label('Ouvrir le site')->url($preview->url($this->record), shouldOpenInNewTab: true)->button(),
            ])
            ->send();
    }
}
