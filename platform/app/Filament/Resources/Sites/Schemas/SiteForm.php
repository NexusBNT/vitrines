<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Enums\ServerStatus;
use App\Enums\SiteStyle;
use App\Models\Client;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SiteForm
{
    public const DAYS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        self::siteTab(),
                        self::businessTab(),
                        self::contactTab(),
                        self::hoursTab(),
                        self::areaTab(),
                        self::styleTab(),
                    ]),
            ]);
    }

    private static function siteTab(): Tab
    {
        return Tab::make('Site')
            ->columns(2)
            ->schema([
                Select::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'company_name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(self::prefillFromClient(...)),
                Select::make('plan_id')
                    ->label('Offre')
                    ->relationship('plan', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'))
                    ->required(),
                TextInput::make('slug')
                    ->label('Identifiant')
                    ->helperText('Minuscules, chiffres et tirets. Sert à l\'adresse de prévisualisation.')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true),
                Select::make('theme')
                    ->label('Thème')
                    ->options(config('vitrines.themes'))
                    ->default(array_key_first(config('vitrines.themes')))
                    ->required(),
                Select::make('server_id')
                    ->label('Serveur')
                    ->relationship('server', 'name', fn (Builder $query) => $query->where('status', ServerStatus::Active))
                    ->helperText('Laisser vide : attribué automatiquement à la publication.'),
            ]);
    }

    private static function businessTab(): Tab
    {
        return Tab::make('Activité')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('brief.business_name')
                        ->label('Nom affiché de l\'entreprise')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('brief.activity')
                        ->label('Activité / métier')
                        ->placeholder('Plombier chauffagiste')
                        ->required()
                        ->maxLength(120),
                ]),
                Textarea::make('brief.description')
                    ->label('Description')
                    ->helperText('Histoire, savoir-faire, points forts réels (certifications, années d\'expérience…). L\'IA n\'inventera rien qui ne figure pas ici.')
                    ->rows(5)
                    ->maxLength(3000),
                Repeater::make('brief.services')
                    ->label('Services')
                    ->schema([
                        TextInput::make('name')
                            ->label('Service')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('description')
                            ->label('Précisions (facultatif)')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->addActionLabel('Ajouter un service')
                    ->minItems(1)
                    ->maxItems(12)
                    ->defaultItems(1)
                    ->collapsible()
                    ->reorderable(),
                Textarea::make('brief.notes')
                    ->label('Consignes pour la rédaction')
                    ->helperText('Ton souhaité, éléments à mettre en avant ou à éviter.')
                    ->rows(3)
                    ->maxLength(2000),
            ]);
    }

    private static function contactTab(): Tab
    {
        return Tab::make('Coordonnées')
            ->columns(2)
            ->schema([
                TextInput::make('brief.phone')
                    ->label('Téléphone affiché')
                    ->tel()
                    ->maxLength(32),
                TextInput::make('brief.email')
                    ->label('Email affiché')
                    ->helperText('Reçoit aussi les messages du formulaire.')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('brief.address.street')
                    ->label('Adresse')
                    ->helperText('Laisser vide pour ne pas afficher d\'adresse (activité sans local).')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('brief.address.postal_code')
                    ->label('Code postal')
                    ->maxLength(10),
                TextInput::make('brief.address.city')
                    ->label('Ville')
                    ->maxLength(120),
                TextInput::make('brief.socials.facebook')
                    ->label('Facebook')
                    ->url()
                    ->placeholder('https://…'),
                TextInput::make('brief.socials.instagram')
                    ->label('Instagram')
                    ->url()
                    ->placeholder('https://…'),
                TextInput::make('brief.socials.linkedin')
                    ->label('LinkedIn')
                    ->url()
                    ->placeholder('https://…'),
                TextInput::make('brief.socials.google_business')
                    ->label('Fiche Google Business')
                    ->url()
                    ->placeholder('https://…'),
            ]);
    }

    private static function hoursTab(): Tab
    {
        return Tab::make('Horaires')
            ->schema([
                Repeater::make('brief.opening_hours')
                    ->label('Plages d\'ouverture')
                    ->schema([
                        CheckboxList::make('days')
                            ->label('Jours')
                            ->options(self::DAYS)
                            ->columns(7)
                            ->required()
                            ->columnSpanFull(),
                        TimePicker::make('opens')
                            ->label('Ouverture')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('closes')
                            ->label('Fermeture')
                            ->seconds(false)
                            ->after('opens')
                            ->required(),
                    ])
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => self::describeHours($state))
                    ->addActionLabel('Ajouter une plage')
                    ->defaultItems(0)
                    ->collapsible(),
                TextInput::make('brief.opening_hours_note')
                    ->label('Précision')
                    ->placeholder('Interventions d\'urgence 7j/7')
                    ->maxLength(255),
            ]);
    }

    private static function areaTab(): Tab
    {
        return Tab::make('Zone')
            ->columns(2)
            ->schema([
                TextInput::make('brief.city')
                    ->label('Ville principale')
                    ->helperText('Ville ciblée pour le référencement local.')
                    ->required()
                    ->maxLength(120),
                TextInput::make('brief.service_radius_km')
                    ->label('Rayon d\'intervention')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(500)
                    ->suffix('km'),
                TagsInput::make('brief.service_area')
                    ->label('Communes desservies')
                    ->helperText('Mentionnées dans le texte, sans créer de page par ville.')
                    ->placeholder('Ajouter une commune')
                    ->splitKeys(['Tab', ','])
                    ->columnSpanFull(),
            ]);
    }

    private static function styleTab(): Tab
    {
        return Tab::make('Style')
            ->columns(3)
            ->schema([
                ColorPicker::make('brief.colors.primary')
                    ->label('Couleur principale')
                    ->regex('/^#[0-9a-fA-F]{6}$/')
                    ->default('#1d4ed8')
                    ->required(),
                ColorPicker::make('brief.colors.secondary')
                    ->label('Couleur secondaire')
                    ->regex('/^#[0-9a-fA-F]{6}$/'),
                Select::make('brief.style')
                    ->label('Ambiance')
                    ->options(SiteStyle::class)
                    ->default(SiteStyle::Modern->value)
                    ->required(),
            ]);
    }

    /**
     * Préremplit le brief avec les coordonnées du client, sans écraser une saisie existante.
     */
    private static function prefillFromClient(Get $get, Set $set, ?string $state): void
    {
        $client = Client::find($state);

        if ($client === null) {
            return;
        }

        $defaults = [
            'brief.business_name' => $client->company_name,
            'brief.email' => $client->email,
            'brief.phone' => $client->phone,
            'brief.address.street' => $client->address_line,
            'brief.address.postal_code' => $client->postal_code,
            'brief.address.city' => $client->city,
            'brief.city' => $client->city,
            'slug' => Str::slug($client->company_name),
        ];

        foreach ($defaults as $path => $value) {
            if (blank($get($path)) && filled($value)) {
                $set($path, $value);
            }
        }
    }

    /**
     * @param  array{days?: list<string>, opens?: ?string, closes?: ?string}  $state
     */
    private static function describeHours(array $state): ?string
    {
        if (empty($state['days'])) {
            return null;
        }

        $days = collect($state['days'])->map(fn (string $day): string => self::DAYS[$day] ?? $day)->implode(', ');

        return sprintf('%s : %s – %s', $days, substr($state['opens'] ?? '', 0, 5), substr($state['closes'] ?? '', 0, 5));
    }
}
