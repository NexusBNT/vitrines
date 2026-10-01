<?php

namespace App\Filament\Resources\Sites;

use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Pages\EditSiteDesign;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Sites\Schemas\SiteForm;
use App\Filament\Resources\Sites\Tables\SitesTable;
use App\Models\Site;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $modelLabel = 'site';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return SiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MediaRelationManager::class,
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        /** @var Site $site */
        $site = $page->getRecord();

        return [
            ...$page->generateNavigationItems([EditSite::class]),
            NavigationItem::make('Pages')
                ->icon(Heroicon::OutlinedDocumentText)
                ->url(route('filament.admin.sites.editor', $site)),
            ...$page->generateNavigationItems([EditSiteDesign::class]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'create' => CreateSite::route('/create'),
            'edit' => EditSite::route('/{record}/edit'),
            'design' => EditSiteDesign::route('/{record}/design'),
        ];
    }
}
