<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Domain\Generation\AiManager;
use App\Domain\Media\StoreUploadedMedia;
use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use App\Jobs\DescribeMedia;
use App\Models\Media;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Photos';

    protected static ?string $modelLabel = 'photo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('alt')
                    ->label('Texte alternatif')
                    ->helperText('Décrit la photo pour les malvoyants et Google. Ex. : « Salle de bain rénovée avec douche à l\'italienne ».')
                    ->maxLength(255),
                TextInput::make('caption')
                    ->label('Légende')
                    ->maxLength(255),
                Select::make('category')
                    ->label('Usage')
                    ->options(fn (?Media $record): array => self::categoryOptions($record))
                    ->helperText(fn (?Media $record): ?string => $record?->isAiGenerated() ? 'Une illustration IA ne peut pas être présentée comme une réalisation, une photo d\'équipe ou les locaux de l\'entreprise.' : null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->poll(fn (): ?string => $this->getOwnerRecord()->media()->where('status', MediaStatus::Pending)->exists() ? '3s' : null)
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Media $record): ?string => $record->thumbnailPath() === null
                        ? null
                        : route('filament.admin.media.thumbnail', $record))
                    ->imageHeight(64),
                TextInputColumn::make('alt')
                    ->label('Texte alternatif')
                    ->rules(['nullable', 'max:255']),
                SelectColumn::make('category')
                    ->label('Usage')
                    ->options(fn (Media $record): array => self::categoryOptions($record)),
                TextColumn::make('source')
                    ->label('Origine')
                    ->badge(),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->tooltip(fn (Media $record): ?string => $record->error),
                TextColumn::make('width')
                    ->label('Taille')
                    ->formatStateUsing(fn (Media $record): string => "{$record->width} × {$record->height}")
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Ajouter des photos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Photos')
                            ->helperText('JPEG, PNG ou WebP. Elles sont converties automatiquement en AVIF/WebP optimisés.')
                            ->multiple()
                            ->maxFiles(30)
                            ->maxParallelUploads(3)
                            ->disk(config('vitrines.media.disk'))
                            ->directory('incoming')
                            ->acceptedFileTypes(config('vitrines.media.accepted_mimes'))
                            ->maxSize(config('vitrines.media.max_upload_kb'))
                            ->storeFileNamesIn('names')
                            ->required(),
                    ])
                    ->action(function (array $data, StoreUploadedMedia $store): void {
                        $this->storeUploads($data, $store);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('describe')
                        ->label('Décrire par IA')
                        ->icon('heroicon-o-sparkles')
                        ->visible(fn (AiManager $ai): bool => $ai->isAvailable('alt_text'))
                        ->action(function (Collection $records): void {
                            $records->where('status', MediaStatus::Ready)->each(fn (Media $media) => DescribeMedia::dispatch($media));

                            Notification::make()
                                ->title('Description lancée')
                                ->body('Les textes alternatifs vides seront complétés d\'ici quelques instants.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Usages autorisés : une illustration IA ne peut jamais passer pour une photo réelle de l'entreprise.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(?Media $record): array
    {
        $excluded = $record?->isAiGenerated() ? [MediaCategory::Work, MediaCategory::Team, MediaCategory::Premises] : [];

        return collect(MediaCategory::cases())
            ->reject(fn (MediaCategory $category): bool => in_array($category, $excluded, true))
            ->mapWithKeys(fn (MediaCategory $category): array => [$category->value => $category->getLabel()])
            ->all();
    }

    /**
     * @param  array{photos: list<string>, names?: array<string, string>}  $data
     */
    private function storeUploads(array $data, StoreUploadedMedia $store): void
    {
        /** @var Site $site */
        $site = $this->getOwnerRecord();
        $rejected = [];

        foreach ($data['photos'] as $path) {
            try {
                $store->handle($site, $path, $data['names'][$path] ?? basename($path));
            } catch (ValidationException $exception) {
                $rejected[] = $exception->validator->errors()->first();
            }
        }

        if ($rejected !== []) {
            Notification::make()
                ->title('Certains fichiers ont été refusés')
                ->body(implode("\n", $rejected))
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
