<?php

namespace App\Filament\Resources\Servers;

use App\Enums\ServerStatus;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $modelLabel = 'serveur';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->alphaDash()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->label('Statut')
                    ->options(ServerStatus::class)
                    ->default(ServerStatus::Active)
                    ->required(),
                TextInput::make('ipv4')
                    ->label('IPv4 publique des sites')
                    ->required()
                    ->ipv4(),
                TextInput::make('ipv6')
                    ->label('IPv6 publique des sites')
                    ->ipv6(),
                TextInput::make('ssh_host')
                    ->label('Hôte SSH')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ssh_port')
                    ->label('Port SSH')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->default(22)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom'),
                TextColumn::make('ipv4')
                    ->label('IPv4'),
                TextColumn::make('ssh_host')
                    ->label('SSH')
                    ->formatStateUsing(fn (Server $record): string => "{$record->ssh_host}:{$record->ssh_port}"),
                TextColumn::make('sites_count')
                    ->label('Sites')
                    ->counts('sites'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServers::route('/'),
        ];
    }
}
