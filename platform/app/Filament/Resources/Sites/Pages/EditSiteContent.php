<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Domain\Build\BuildPreview;
use App\Enums\MediaStatus;
use App\Filament\Resources\Sites\Pages\Concerns\BuildsPreview;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\AuditLog;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Édition manuelle des textes et des photos d'un site, page par page et section par section.
 *
 * @property Site $record
 */
class EditSiteContent extends EditRecord
{
    use BuildsPreview;

    protected static string $resource = SiteResource::class;

    protected static ?string $title = 'Textes du site';

    protected static ?string $navigationLabel = 'Textes';

    private const SECTION_LABELS = [
        'hero' => 'Bandeau d\'accueil',
        'page_header' => 'En-tête de page',
        'services' => 'Services',
        'about' => 'Présentation',
        'highlights' => 'Points forts',
        'gallery' => 'Galerie',
        'zone' => 'Zone d\'intervention',
        'faq' => 'Questions fréquentes',
        'cta' => 'Appel à l\'action',
        'contact' => 'Contact',
    ];

    public function form(Schema $schema): Schema
    {
        $spec = $this->record->draft_spec;

        if ($spec === null) {
            return $schema->components([
                Callout::make('Aucun contenu pour l\'instant')
                    ->description('Générez les textes depuis l\'onglet « Paramètres » (avec ou sans IA), puis revenez ici pour les ajuster.')
                    ->info(),
            ]);
        }

        return $schema->components([
            ...$this->notices($spec),
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs(collect($spec['pages'])->map(fn (array $page, int $index): Tab => $this->pageTab($page, $index))->all()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Enregistrer et prévisualiser')
                ->icon(Heroicon::OutlinedEye)
                ->visible(fn (): bool => $this->record->draft_spec !== null)
                ->action(function (BuildPreview $preview): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->buildPreview($preview);
                }),
        ];
    }

    /**
     * Les champs non affichés (type de section, ancres, liens…) sont conservés :
     * les valeurs saisies sont fusionnées dans la spécification existante.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $spec = $this->record->draft_spec;

        foreach ($data['draft_spec']['pages'] ?? [] as $pageIndex => $editedPage) {
            $editedSections = $editedPage['sections'] ?? [];
            unset($editedPage['sections']);

            $spec['pages'][$pageIndex] = array_merge($spec['pages'][$pageIndex], $editedPage);

            foreach ($editedSections as $sectionIndex => $editedSection) {
                $spec['pages'][$pageIndex]['sections'][$sectionIndex] = array_merge(
                    $spec['pages'][$pageIndex]['sections'][$sectionIndex],
                    $editedSection,
                );
            }
        }

        return ['draft_spec' => $spec];
    }

    protected function afterSave(): void
    {
        AuditLog::record('content_edited', $this->record);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<Component>
     */
    private function notices(array $spec): array
    {
        $notices = [];
        $warnings = $this->record->settings['last_generation']['warnings'] ?? [];

        if (! empty($spec['suggestions'])) {
            $notices[] = Callout::make('Suggestions de l\'IA')
                ->description(new HtmlString(collect($spec['suggestions'])->map(fn (string $item): string => '• '.e($item))->implode('<br>')))
                ->info();
        }

        if ($warnings !== []) {
            $notices[] = Callout::make('Points à vérifier')
                ->description(new HtmlString(collect($warnings)->map(fn (string $item): string => '• '.e($item))->implode('<br>')))
                ->warning();
        }

        if (($spec['generated_by'] ?? null) === 'draft') {
            $notices[] = Callout::make('Brouillon sans IA')
                ->description('Ces textes reprennent simplement le brief. Utilisez « Rédiger avec l\'IA » dans l\'onglet Paramètres pour une rédaction complète.')
                ->warning();
        }

        return $notices;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function pageTab(array $page, int $index): Tab
    {
        $path = "draft_spec.pages.{$index}";

        return Tab::make($page['nav_label'])
            ->schema([
                Section::make('Référencement')
                    ->description('Titre et description affichés dans les résultats Google.')
                    ->columns(1)
                    ->collapsible()
                    ->schema([
                        TextInput::make("{$path}.title")
                            ->label('Titre (balise title)')
                            ->required()
                            ->maxLength(70)
                            ->hint(fn (?string $state): string => mb_strlen((string) $state).' / 65')
                            ->live(onBlur: true),
                        Textarea::make("{$path}.meta_description")
                            ->label('Meta description')
                            ->required()
                            ->rows(2)
                            ->maxLength(170)
                            ->hint(fn (?string $state): string => mb_strlen((string) $state).' / 155')
                            ->live(onBlur: true),
                        TextInput::make("{$path}.nav_label")
                            ->label('Libellé dans le menu')
                            ->required()
                            ->maxLength(30)
                            ->visible($page['key'] !== 'home'),
                    ]),
                ...collect($page['sections'])
                    ->map(fn (array $section, int $sectionIndex): Section => $this->sectionEditor($section, "{$path}.sections.{$sectionIndex}"))
                    ->all(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function sectionEditor(array $section, string $path): Section
    {
        $fields = match ($section['type']) {
            'hero' => [
                TextInput::make("{$path}.h1")->label('Titre principal (H1)')->required()->maxLength(90),
                Textarea::make("{$path}.lead")->label('Accroche')->rows(2)->maxLength(300),
                $this->imageSelect("{$path}.image", 'Photo'),
                Select::make("{$path}.variant")->label('Mise en page')->options([
                    'split' => 'Texte et photo côte à côte',
                    'image' => 'Photo en plein écran',
                    'plain' => 'Texte seul',
                ])->required(),
            ],
            'page_header' => [
                TextInput::make("{$path}.h1")->label('Titre principal (H1)')->required()->maxLength(90),
                Textarea::make("{$path}.lead")->label('Accroche')->rows(2)->maxLength(300),
            ],
            'services' => [
                ...$this->headingFields($path),
                Repeater::make("{$path}.items")
                    ->label('Services')
                    ->schema([
                        TextInput::make('name')->label('Nom')->required()->maxLength(120),
                        Textarea::make('text')->label('Description')->rows(4)->maxLength(2000),
                        $this->imageSelect('image', 'Photo'),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->collapsible()
                    ->reorderable()
                    ->addActionLabel('Ajouter un service'),
            ],
            'about' => [
                TextInput::make("{$path}.heading")->label('Titre')->maxLength(120),
                $this->paragraphsField("{$path}.paragraphs"),
                $this->imageSelect("{$path}.image", 'Photo'),
            ],
            'highlights' => [
                TextInput::make("{$path}.heading")->label('Titre')->maxLength(120),
                Repeater::make("{$path}.items")
                    ->label('Points forts')
                    ->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(120),
                        Textarea::make('text')->label('Texte')->rows(2)->maxLength(500),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->collapsible()
                    ->reorderable(),
            ],
            'gallery' => [
                ...$this->headingFields($path),
                Select::make("{$path}.images")
                    ->label('Photos affichées')
                    ->multiple()
                    ->options(fn (): array => $this->mediaOptions())
                    ->dehydrateStateUsing(fn (?array $state): array => array_map(intval(...), $state ?? [])),
            ],
            'zone' => [
                TextInput::make("{$path}.heading")->label('Titre')->maxLength(120),
                Textarea::make("{$path}.text")->label('Texte')->rows(3)->maxLength(1000),
                TagsInput::make("{$path}.towns")->label('Communes affichées'),
            ],
            'faq' => [
                TextInput::make("{$path}.heading")->label('Titre')->maxLength(120),
                Repeater::make("{$path}.items")
                    ->label('Questions')
                    ->schema([
                        TextInput::make('question')->label('Question')->required()->maxLength(200),
                        Textarea::make('answer')->label('Réponse')->required()->rows(3)->maxLength(1500),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                    ->collapsible()
                    ->reorderable()
                    ->addActionLabel('Ajouter une question'),
            ],
            'cta' => [
                TextInput::make("{$path}.heading")->label('Titre')->required()->maxLength(120),
                Textarea::make("{$path}.text")->label('Texte')->rows(2)->maxLength(300),
            ],
            'contact' => [
                TextInput::make("{$path}.heading")->label('Titre')->maxLength(120),
                Textarea::make("{$path}.text")->label('Texte au-dessus du formulaire')->rows(2)->maxLength(500),
                Toggle::make("{$path}.show_map")->label('Proposer la carte Google Maps'),
            ],
            default => [],
        };

        return Section::make(self::SECTION_LABELS[$section['type']] ?? $section['type'])
            ->collapsible()
            ->compact()
            ->schema($fields);
    }

    /**
     * @return list<Component>
     */
    private function headingFields(string $path): array
    {
        return [
            TextInput::make("{$path}.heading")->label('Titre de la section')->maxLength(120),
            Textarea::make("{$path}.intro")->label('Introduction')->rows(2)->maxLength(500),
        ];
    }

    private function paragraphsField(string $path): Textarea
    {
        return Textarea::make($path)
            ->label('Texte')
            ->helperText('Séparez les paragraphes par une ligne vide.')
            ->rows(8)
            ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode("\n\n", $state) : (string) $state)
            ->dehydrateStateUsing(fn (?string $state): array => array_values(array_filter(array_map(trim(...), preg_split('/\R{2,}/u', (string) $state) ?: []))));
    }

    private function imageSelect(string $path, string $label): Select
    {
        return Select::make($path)
            ->label($label)
            ->options(fn (): array => $this->mediaOptions())
            ->placeholder('Aucune')
            ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? (int) $state : null);
    }

    /**
     * @return array<int, string>
     */
    private function mediaOptions(): array
    {
        return once(fn (): array => $this->record->media()
            ->where('status', MediaStatus::Ready)
            ->get()
            ->mapWithKeys(fn ($media): array => [$media->id => $media->alt ?: $media->original_name])
            ->all());
    }
}
