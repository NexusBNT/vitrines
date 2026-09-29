<?php

namespace App\Filament\Resources\Plans;

use App\Enums\PlanFeature;
use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $modelLabel = 'offre';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(64),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->alphaDash()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('max_pages')
                    ->label('Pages maximum')
                    ->helperText('Hors mentions légales et confidentialité. 1 = site une page.')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(20),
                TextInput::make('sort_order')
                    ->label('Ordre')
                    ->integer()
                    ->default(0),
                CheckboxList::make('features')
                    ->label('Fonctionnalités incluses')
                    ->options(PlanFeature::class)
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Proposée aux nouveaux sites')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom'),
                TextColumn::make('max_pages')
                    ->label('Pages'),
                TextColumn::make('features')
                    ->label('Fonctionnalités')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PlanFeature::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('sites_count')
                    ->label('Sites')
                    ->counts('sites'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
