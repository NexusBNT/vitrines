<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\ClientStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Raison sociale')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Téléphone'),
                TextColumn::make('city')
                    ->label('Ville')
                    ->searchable(),
                TextColumn::make('sites_count')
                    ->label('Sites')
                    ->counts('sites'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('company_name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ClientStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
