<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Enums\SiteStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brief.business_name')
                    ->label('Entreprise')
                    ->description(fn ($record): string => $record->slug)
                    ->searchable(['slug', 'brief->business_name']),
                TextColumn::make('client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brief.city')
                    ->label('Ville'),
                TextColumn::make('plan.name')
                    ->label('Offre')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('server.name')
                    ->label('Serveur')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'plan', 'server']))
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(SiteStatus::class),
                SelectFilter::make('plan')
                    ->label('Offre')
                    ->relationship('plan', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
