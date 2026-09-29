<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Domain\Build\BuildPreview;
use App\Domain\Sites\DraftSpecFactory;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\AuditLog;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * @property Site $record
 */
class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateDraft')
                ->label(fn (): string => $this->record->draft_spec === null ? 'Générer le brouillon' : 'Régénérer le brouillon')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation(fn (): bool => $this->record->draft_spec !== null)
                ->modalDescription('Le contenu actuel du site sera remplacé par un nouveau brouillon construit à partir du brief.')
                ->action(function (DraftSpecFactory $factory): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->record->update(['draft_spec' => $factory->make($this->record->refresh())]);
                    AuditLog::record('draft_generated', $this->record);

                    Notification::make()->title('Brouillon généré')->body('Cliquez sur « Prévisualiser » pour voir le site.')->success()->send();
                }),
            Action::make('preview')
                ->label('Prévisualiser')
                ->icon(Heroicon::OutlinedEye)
                ->disabled(fn (): bool => $this->record->draft_spec === null)
                ->tooltip(fn (): ?string => $this->record->draft_spec === null ? 'Générez d\'abord le brouillon.' : null)
                ->action(function (BuildPreview $preview): void {
                    $this->buildPreview($preview);
                }),
        ];
    }

    private function buildPreview(BuildPreview $preview): void
    {
        try {
            $result = $preview->handle($this->record);
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
                Action::make('open')
                    ->label('Ouvrir le site')
                    ->url($preview->url($this->record), shouldOpenInNewTab: true)
                    ->button(),
            ])
            ->send();
    }
}
