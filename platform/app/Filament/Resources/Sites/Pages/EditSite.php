<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\AiManager;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\PlanFeature;
use App\Filament\Resources\Sites\Pages\Concerns\BuildsPreview;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\GenerateFullSite;
use App\Jobs\GenerateSiteContent;
use App\Models\AuditLog;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Site $record
 */
class EditSite extends EditRecord
{
    use BuildsPreview;

    protected static ?string $navigationLabel = 'Paramètres';

    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        $aiAvailable = app(AiManager::class)->isAvailable('site_content');

        return [
            Action::make('generateFullSite')
                ->label('Génération intégrale (IA)')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('success')
                ->visible(fn (): bool => $this->record->plan->hasFeature(PlanFeature::AiFull))
                ->disabled(! $aiAvailable)
                ->tooltip($aiAvailable ? null : 'Ajoutez une clé API dans le fichier .env.')
                ->modalHeading('Génération intégrale du site')
                ->modalDescription('L\'IA choisit un design (si aucun n\'est retenu), crée les illustrations manquantes, puis rédige tous les textes. Comptez 3 à 5 minutes ; le résultat arrive dans la cloche des notifications. Les textes actuels seront remplacés.')
                ->modalSubmitActionLabel('Tout générer')
                ->schema([
                    Textarea::make('instructions')
                        ->label('Consignes (facultatif)')
                        ->placeholder('Ex. : ambiance haut de gamme, mettre en avant la rénovation.')
                        ->rows(3)
                        ->maxLength(1000),
                    Toggle::make('replace_illustrations')
                        ->label('Remplacer les illustrations IA existantes')
                        ->helperText('Les photos du client ne sont jamais supprimées.'),
                ])
                ->action(function (array $data): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    GenerateFullSite::dispatch($this->record, auth()->user(), $data['instructions'] ?? null, (bool) ($data['replace_illustrations'] ?? false));

                    Notification::make()
                        ->title('Génération intégrale lancée')
                        ->body('Comptez 3 à 5 minutes. Le résultat arrivera dans la cloche des notifications.')
                        ->success()
                        ->send();
                }),
            Action::make('generateWithAi')
                ->label('Rédiger avec l\'IA')
                ->icon(Heroicon::OutlinedSparkles)
                ->disabled(! $aiAvailable)
                ->tooltip($aiAvailable ? null : 'Ajoutez une clé API (ANTHROPIC_API_KEY ou OPENAI_API_KEY) dans le fichier .env.')
                ->modalHeading('Rédiger les textes avec l\'IA')
                ->modalDescription(fn (): string => $this->record->draft_spec === null
                    ? 'Les textes sont rédigés à partir du brief, en environ une minute. Vous serez prévenu dans la cloche en haut à droite.'
                    : 'Les textes actuels du site seront remplacés, y compris vos retouches manuelles. Vous serez prévenu dans la cloche en haut à droite.')
                ->modalSubmitActionLabel('Lancer la rédaction')
                ->schema([
                    Textarea::make('instructions')
                        ->label('Consignes pour cette version (facultatif)')
                        ->placeholder('Ex. : ton plus chaleureux, mettre en avant la rénovation de salles de bain.')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    GenerateSiteContent::dispatch($this->record, auth()->user(), $data['instructions'] ?? null);

                    Notification::make()
                        ->title('Rédaction lancée')
                        ->body('Environ une minute. Le résultat arrivera dans la cloche des notifications.')
                        ->success()
                        ->send();
                }),
            Action::make('preview')
                ->label('Prévisualiser')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->disabled(fn (): bool => $this->record->draft_spec === null)
                ->tooltip(fn (): ?string => $this->record->draft_spec === null ? 'Rédigez d\'abord les textes.' : null)
                ->action(function (BuildPreview $preview): void {
                    $this->buildPreview($preview);
                }),
            ActionGroup::make([
                Action::make('generateDraft')
                    ->label('Brouillon sans IA')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->requiresConfirmation(fn (): bool => $this->record->draft_spec !== null)
                    ->modalDescription('Le contenu actuel du site sera remplacé par un brouillon qui reprend simplement le brief.')
                    ->action(function (DraftSpecFactory $factory): void {
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        $this->record->update(['draft_spec' => $factory->make($this->record->refresh())]);
                        AuditLog::record('draft_generated', $this->record);

                        Notification::make()->title('Brouillon généré')->body('Cliquez sur « Prévisualiser » pour voir le site.')->success()->send();
                    }),
            ]),
        ];
    }
}
