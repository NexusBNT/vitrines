<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\ClientStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entreprise')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Raison sociale')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('siret')
                            ->label('SIRET')
                            ->regex('/^\d{14}$/')
                            ->validationMessages(['regex' => 'Le SIRET comporte 14 chiffres.']),
                        TextInput::make('address_line')
                            ->label('Adresse')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('postal_code')
                            ->label('Code postal')
                            ->maxLength(10),
                        TextInput::make('city')
                            ->label('Ville')
                            ->maxLength(255),
                    ]),
                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Nom du contact')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(32),
                        Select::make('status')
                            ->label('Statut')
                            ->options(ClientStatus::class)
                            ->default(ClientStatus::Active)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes internes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
