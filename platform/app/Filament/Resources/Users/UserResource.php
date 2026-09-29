<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->rule(Password::min(12))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Laisser vide pour conserver le mot de passe actuel.' : null),
                Select::make('role')
                    ->label('Rôle')
                    ->options(UserRole::class)
                    ->default(UserRole::Editor)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                Toggle::make('is_active')
                    ->label('Accès autorisé')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),
                IconColumn::make('two_factor')
                    ->label('2FA')
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetTwoFactor')
                    ->label('Réinitialiser la 2FA')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('L\'utilisateur devra reconfigurer son application d\'authentification à sa prochaine connexion.')
                    ->visible(fn (User $record): bool => filled($record->app_authentication_secret) && ! $record->is(auth()->user()))
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        AuditLog::record('two_factor_reset', $record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
