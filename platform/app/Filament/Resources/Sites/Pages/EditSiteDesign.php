<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\AiManager;
use App\Domain\Generation\GenerationProgress;
use App\Domain\Sites\ApplyDesign;
use App\Domain\Sites\Design;
use App\Domain\Sites\DesignWireframe;
use App\Domain\Sites\DraftSpecFactory;
use App\Domain\Sites\SiteTemplates;
use App\Filament\Resources\Sites\Pages\Concerns\BuildsPreview;
use App\Filament\Resources\Sites\Pages\Concerns\ShowsGenerationProgress;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\GenerateDesignProposals;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
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
    use BuildsPreview, ShowsGenerationProgress;

    protected static string $resource = SiteResource::class;

    protected static ?string $title = 'Design du site';

    protected static ?string $navigationLabel = 'Design';

    /**
     * Choisir un thème garde-t-il les couleurs actuelles du site ?
     */
    public bool $keepColors = true;

    public function form(Schema $schema): Schema
    {
        $components = [];
        $proposals = $this->record->settings['design_proposals']['items'] ?? [];

        if ($proposals !== []) {
            $components[] = Section::make('Propositions de l\'IA')
                ->description('Trois directions différentes, structure comprise. Prévisualisez-les, choisissez-en une puis ajustez-la.')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    View::make('filament.sites.design-proposals')->viewData(fn (): array => ['proposals' => $this->presentProposals($proposals)]),
                ]);
        }

        $components[] = Grid::make(['default' => 1, 'xl' => 3])->columnSpanFull()->schema([
            Tabs::make('design')
                ->columnSpan(['default' => 1, 'xl' => 2])
                ->persistTabInQueryString('onglet')
                ->tabs([
                    Tab::make('Thème')
                        ->icon(Heroicon::OutlinedSwatch)
                        ->schema([
                            View::make('filament.sites.design-templates')->viewData(fn (): array => ['templates' => $this->presentTemplates()]),
                        ]),
                    Tab::make('Structure')
                        ->icon(Heroicon::OutlinedSquares2x2)
                        ->schema([
                            Text::make('Disposition des éléments sur toutes les pages. L\'aperçu à droite se met à jour à chaque choix ; pensez à enregistrer.'),
                            ...collect(Design::STRUCTURE)->keys()->map(fn (string $token): ViewField => $this->layoutPicker($token))->all(),
                        ]),
                    Tab::make('Style et couleurs')
                        ->icon(Heroicon::OutlinedPaintBrush)
                        ->columns(2)
                        ->schema([
                            ColorPicker::make('settings.design.primary')->label('Couleur principale')->regex('/^#[0-9a-fA-F]{6}$/')->required()->live(),
                            ColorPicker::make('settings.design.secondary')->label('Couleur secondaire')->regex('/^#[0-9a-fA-F]{6}$/')->live(),
                            Select::make('settings.design.font_pair')->label('Polices')->options(collect(Design::FONT_PAIRS)->map(fn (array $pair): string => $pair['label']))->required()->columnSpanFull(),
                            ...collect(Design::STYLE)->map(fn (array $choices, string $token): Select => Select::make("settings.design.{$token}")
                                ->label(Design::LABELS[$token])
                                ->options($choices)
                                ->required()
                                ->live())->values()->all(),
                        ]),
                ]),
            Section::make('Aperçu')
                ->description('Schéma de la page d\'accueil avec les réglages en cours.')
                ->columnSpan(1)
                ->extraAttributes(['style' => 'position:sticky;top:5rem;align-self:start'])
                ->schema([
                    View::make('filament.sites.design-wireframe')->viewData(fn (Get $get): array => [
                        'svg' => DesignWireframe::svg($this->formDesign($get)),
                    ]),
                ]),
        ]);

        return $schema->components($components);
    }

    /**
     * Sélecteur visuel d'un jeton de structure : une miniature par choix, cadrée sur la zone concernée.
     */
    private function layoutPicker(string $token): ViewField
    {
        return ViewField::make("settings.design.{$token}")
            ->label(Design::LABELS[$token])
            ->view('filament.sites.layout-picker')
            ->viewData(fn (Get $get): array => [
                'options' => collect(Design::STRUCTURE[$token])->map(fn (string $label, string $value): array => [
                    'label' => $label,
                    'svg' => DesignWireframe::svg([...$this->illustrationContext($token, $this->formDesign($get)), $token => $value], $token),
                ])->all(),
            ])
            ->helperText(fn (Get $get): ?string => $this->inactiveReason($token, $this->formDesign($get)))
            ->live()
            ->required();
    }

    /**
     * Certains réglages ne se voient qu'avec une autre structure : la miniature l'emprunte pour les montrer.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function illustrationContext(string $token, array $design): array
    {
        if (in_array($token, ['topbar', 'header_overlay'], true) && str_starts_with($design['nav_layout'], 'sidebar')) {
            $design['nav_layout'] = 'top';
        }

        if ($token === 'header_overlay' && ! in_array($design['hero_layout'], ['image', 'boxed'], true)) {
            $design['hero_layout'] = 'image';
        }

        if ($token === 'hero_align' && ! in_array($design['hero_layout'], ['image', 'boxed', 'plain'], true)) {
            $design['hero_layout'] = 'image';
        }

        return $design;
    }

    /**
     * @param  array<string, mixed>  $design
     */
    private function inactiveReason(string $token, array $design): ?string
    {
        $sidebar = str_starts_with($design['nav_layout'], 'sidebar');

        return match (true) {
            $token === 'topbar' && $sidebar => 'Sans effet avec un menu latéral : les coordonnées y sont déjà affichées.',
            $token === 'header_overlay' && $sidebar => 'Sans effet avec un menu latéral.',
            $token === 'header_overlay' && ! in_array($design['hero_layout'], ['image', 'boxed'], true) => 'S\'applique seulement avec un bandeau « photo plein écran ».',
            $token === 'hero_align' && ! in_array($design['hero_layout'], ['image', 'boxed', 'plain'], true) => 'S\'applique aux bandeaux « photo plein écran » et « texte seul ».',
            default => null,
        };
    }

    /**
     * Design correspondant à l'état actuel du formulaire (non enregistré).
     *
     * @return array<string, mixed>
     */
    private function formDesign(Get $get): array
    {
        $current = app(DraftSpecFactory::class)->design($this->record);

        return Design::normalize([...$current, ...($get('settings.design') ?? [])], $current);
    }

    protected function getHeaderActions(): array
    {
        $aiAvailable = app(AiManager::class)->isAvailable('design');

        return [
            Action::make('proposeDesigns')
                ->label('Proposer 3 designs (IA)')
                ->icon(Heroicon::OutlinedSparkles)
                ->disabled(fn (): bool => ! $aiAvailable || $this->generationRunning())
                ->tooltip(fn (): ?string => ! $aiAvailable ? 'Ajoutez une clé API dans le fichier .env.' : ($this->generationRunning() ? 'Une génération est déjà en cours.' : null))
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
                    GenerationProgress::queue($this->record, 'design', GenerateDesignProposals::LABEL);
                    GenerateDesignProposals::dispatch($this->record, auth()->user(), $data['instructions'] ?? null);
                    $this->dispatch('generation-queued');

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
     * Applique un thème au site (structure et style), avec ses couleurs ou celles du site.
     */
    public function applyTemplate(string $key, ApplyDesign $apply, BuildPreview $preview): void
    {
        if (! SiteTemplates::exists($key)) {
            return;
        }

        $current = app(DraftSpecFactory::class)->design($this->record);
        $design = $this->keepColors ? SiteTemplates::design($key, $current['primary'], $current['secondary']) : SiteTemplates::design($key);

        $apply->handle($this->record, Design::normalize($design, $design));
        $this->fillForm();

        if ($this->record->draft_spec !== null) {
            $this->buildPreview($preview);
        } else {
            Notification::make()->title('Thème « '.SiteTemplates::ALL[$key]['label'].' » appliqué')->success()->send();
        }
    }

    /**
     * Construit l'aperçu du site avec un thème, sans rien modifier.
     */
    public function previewTemplate(string $key, BuildPreview $preview): void
    {
        if (! SiteTemplates::exists($key)) {
            return;
        }

        $current = app(DraftSpecFactory::class)->design($this->record);
        $design = $this->keepColors ? SiteTemplates::design($key, $current['primary'], $current['secondary']) : SiteTemplates::design($key);
        $result = $preview->designProposal($this->record, $key, Design::normalize($design, $design));

        if ($result->errors() !== []) {
            Notification::make()->title('Aperçu construit avec des points à vérifier')->body(collect($result->errors())->pluck('message')->implode("\n"))->warning()->send();
        }

        $this->js('window.open('.json_encode($preview->designUrl($this->record, $key)).', "_blank")');
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

        return collect($proposals)->map(fn (array $proposal) => Design::normalize($proposal, $current))->map(fn (array $proposal, int $index): array => [
            ...$proposal,
            'fonts' => Design::FONT_PAIRS[$proposal['font_pair']]['label'],
            'summary' => implode(' · ', array_filter([
                SiteTemplates::exists($proposal['template'] ?? null) ? 'Thème '.SiteTemplates::ALL[$proposal['template']]['label'] : null,
                Design::STRUCTURE['nav_layout'][$proposal['nav_layout']],
                Design::STRUCTURE['hero_layout'][$proposal['hero_layout']],
            ])),
            'svg' => DesignWireframe::svg($proposal),
            'preview_url' => $preview->designUrl($this->record, $index),
            'is_current' => collect($proposal)->except(['name', 'rationale'])->all() == collect($current)->except(['name', 'rationale'])->all(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentTemplates(): array
    {
        $current = app(DraftSpecFactory::class)->design($this->record);

        return collect(SiteTemplates::ALL)->map(function (array $template, string $key) use ($current): array {
            $design = SiteTemplates::design($key, ...($this->keepColors ? [$current['primary'], $current['secondary']] : []));

            return [
                ...$template,
                'key' => $key,
                'svg' => DesignWireframe::svg(Design::normalize($design, $design)),
                'fonts' => Design::FONT_PAIRS[$template['style']['font_pair']]['label'],
                'is_current' => $key === ($current['template'] ?? null),
            ];
        })->values()->all();
    }
}
