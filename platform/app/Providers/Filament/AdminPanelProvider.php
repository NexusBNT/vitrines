<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Admin\MediaThumbnailController;
use App\Http\Controllers\Admin\SiteEditorAiController;
use App\Http\Controllers\Admin\SiteEditorApiController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\Admin\SitePreviewController;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Vitrines')
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable()->brandName('Vitrines'),
                isRequired: true,
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('media/{media}/thumbnail', MediaThumbnailController::class)->name('media.thumbnail');
                Route::get('sites/{site}/preview/{path?}', SitePreviewController::class)->where('path', '.*')->name('sites.preview');
                Route::get('sites/{site}/design-preview/{proposal}/{path?}', [SitePreviewController::class, 'design'])
                    ->where('proposal', '[0-9]+|[a-z]+')
                    ->where('path', '.*')
                    ->name('sites.design-preview');

                // Éditeur de pages (application autonome + API JSON)
                Route::get('sites/{site}/editor', [SiteEditorController::class, 'show'])->name('sites.editor');
                Route::get('sites/{site}/editor/theme.css', [SiteEditorController::class, 'theme'])->name('sites.editor.theme');
                Route::get('editor/fonts/{font}.woff2', [SiteEditorController::class, 'font'])->where('font', '[a-z0-9-]+')->name('editor.font');

                Route::prefix('sites/{site}/editor-api')->name('sites.editor-api.')->scopeBindings()->group(function (): void {
                    Route::get('/', [SiteEditorApiController::class, 'bootstrap'])->name('bootstrap');
                    Route::put('pages', [SiteEditorApiController::class, 'save'])->name('save');
                    Route::post('draft', [SiteEditorApiController::class, 'draft'])->name('draft');
                    Route::post('preview', [SiteEditorApiController::class, 'preview'])->name('preview');
                    Route::get('media', [SiteEditorApiController::class, 'media'])->name('media');
                    Route::post('media', [SiteEditorApiController::class, 'upload'])->name('upload');
                    Route::get('revisions', [SiteEditorApiController::class, 'revisions'])->name('revisions');
                    Route::get('revisions/{revision}', [SiteEditorApiController::class, 'revision'])->name('revision');
                    Route::post('revisions/{revision}/restore', [SiteEditorApiController::class, 'restore'])->name('restore');
                    Route::post('ai/transform', [SiteEditorAiController::class, 'transform'])->name('ai.transform');
                    Route::post('ai/write', [SiteEditorAiController::class, 'write'])->name('ai.write');
                    Route::post('ai/seo', [SiteEditorAiController::class, 'seo'])->name('ai.seo');
                });
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
