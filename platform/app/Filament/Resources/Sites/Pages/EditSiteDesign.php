<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\AiManager;
use App\Domain\Sites\ApplyDesign;
use App\Domain\Sites\Design;
use App\Domain\Sites\DraftSpecFactory;
use App\Filament\Resources\Sites\Pages\Concerns\BuildsPreview;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\GenerateDesignProposals;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Choix du design d'un site : propositions de l'IA et réglages manuels des jetons.
 *
 * @property Site $record
 */
class EditSiteDesign extends EditRecord
{
    use BuildsPreview;

    protected static string $resource = SiteResource::class;

    protected static ?string $title = 'Design du site';

    protected static ?string $navigationLabel = 'Design';

    public function form(Schema $schema): Schema
    {
        $components = [];
        $proposals = $this->record->settings['design_proposals']['items'] ?? [];

        if ($proposals !== []) {
            $components[] = Section::make('Propositions de l\'IA')
                ->description('Prévisualisez chaque direction dans un nouvel onglet, puis choisissez celle qui convient. Vous pourrez l\'ajuster ci-dessous.')
                ->schema([
                    View::make('filament.sites.design-proposals')->viewData(['proposals' => $this->presentProposals($proposals)]),
                ]);
        }

        $components[] = Section::make('Design actuel')
            ->description('Chaque réglage s\'applique à toutes les pages. Les couleurs de texte sont ajustées automatiquement pour rester lisibles.')
            ->columns(3)
            ->schema([
                ColorPicker::make('settings.design.primary')->label('Couleur principale')->regex('/^#[0-9a-fA-F]{6}$/')->required(),
                ColorPicker::make('settings.design.secondary')->label('Couleur secondaire')->regex('/^#[0-9a-fA-F]{6}$/'),
                Select::make('settings.design.font_pair')->label('Polices')->options(collect(Design::FONT_PAIRS)->map(fn (array $pair): string => $pair['label']))->required(),
                ...collect(Design::OPTIONS)->map(fn (array $choices, string $token): Select => Select::make("settings.design.{$token}")
                    ->label($this->tokenLabel($token))
                    ->options($choices)
                    ->required())->values()->all(),
            ]);

        return $schema->components($components);
    }

    protected function getHeaderActions(): array
    {
        $aiAvailable = app(AiManager::class)->isAvailable('design');

        return [
            Action::make('proposeDesigns')
                ->label('Proposer 3 designs (IA)')
                ->icon(Heroicon::OutlinedSparkles)
                ->disabled(! $aiAvailable)
                ->tooltip($aiAvailable ? null : 'Ajoutez une clé API dans le fichier .env.')
                ->modalHeading('Propositions de design par IA')
                ->modalDescription('L\'IA analyse le brief et les photos, puis propose trois directions. Comptez une à deux minutes ; le résultat arrive dans la cloche des notifications.')
                ->modalSubmitActionLabel('Lancer')
                ->schema([
                    Textarea::make('instructions')
                        ->label('Consignes (facultatif)')
                        ->placeholder('Ex. : plus haut de gamme, éviter le bleu, s\'accorder avec le logo vert.')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    GenerateDesignProposals::dispatch($this->record, auth()->user(), $data['instructions'] ?? null);

                    Notification::make()->title('Propositions en cours')->body('Le résultat arrivera dans la cloche des notifications.')->success()->send();
                }),
            Action::make('preview')
                ->label('Enregistrer et prévisualiser')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->record->draft_spec !== null)
                ->action(function (BuildPreview $preview): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->buildPreview($preview);
                }),
        ];
    }

    public function applyProposal(int $index, ApplyDesign $apply, BuildPreview $preview): void
    {
        $proposal = $this->record->settings['design_proposals']['items'][$index] ?? null;

        if ($proposal === null) {
            return;
        }

        $apply->handle($this->record, $proposal);
        $this->fillForm();

        if ($this->record->draft_spec !== null) {
            $this->buildPreview($preview);
        } else {
            Notification::make()->title('Design appliqué')->success()->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['settings']['design'] = app(DraftSpecFactory::class)->design($this->record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Site $record */
        $current = app(DraftSpecFactory::class)->design($record);
        $design = Design::normalize([...$current, ...($data['settings']['design'] ?? [])], $current);

        app(ApplyDesign::class)->handle($record, $design);

        return $record;
    }

    /**
     * @param  list<array<string, mixed>>  $proposals
     * @return list<array<string, mixed>>
     */
    private function presentProposals(array $proposals): array
    {
        $current = app(DraftSpecFactory::class)->design($this->record);
        $preview = app(BuildPreview::class);

        return collect($proposals)->map(fn (array $proposal, int $index): array => [
            ...$proposal,
            'swatches' => array_filter(['Principale' => $proposal['primary'], 'Secondaire' => $proposal['secondary']]),
            'fonts' => Design::FONT_PAIRS[$proposal['font_pair']]['label'],
            'summary' => implode(' · ', [
                Design::OPTIONS['header'][$proposal['header']].' (en-tête)',
                Design::OPTIONS['hero_layout'][$proposal['hero_layout']],
                Design::OPTIONS['radius'][$proposal['radius']],
            ]),
            'preview_url' => $preview->designUrl($this->record, $index),
            'is_current' => collect($proposal)->except(['name', 'rationale'])->all() == collect($current)->except(['name', 'rationale'])->all(),
        ])->all();
    }

    private function tokenLabel(string $token): string
    {
        return [
            'radius' => 'Arrondis',
            'buttons' => 'Boutons',
            'shadow' => 'Ombres',
            'header' => 'En-tête',
            'hero_layout' => 'Bandeau d\'accueil',
            'hero_background' => 'Fond du bandeau',
            'cards' => 'Cartes',
            'section_alt' => 'Fond des sections alternées',
            'footer' => 'Pied de page',
            'headings' => 'Titres',
            'density' => 'Espacement',
        ][$token];
    }
}
