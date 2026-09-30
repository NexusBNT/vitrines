<?php

namespace App\Providers;

use App\Domain\Generation\AiManager;
use App\Domain\Generation\Providers\ClaudeProvider;
use App\Domain\Generation\Providers\OpenAiProvider;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiManager::class, fn (): AiManager => new AiManager([
            'claude' => new ClaudeProvider(config('ai.providers.claude')),
            'openai' => new OpenAiProvider(config('ai.providers.openai')),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        View::addNamespace('site', config('vitrines.templates_path'));

        Event::listen(Login::class, fn (Login $event) => AuditLog::record('login', $event->user));
        Event::listen(Failed::class, fn (Failed $event) => AuditLog::record('login_failed', $event->user, [
            'email' => $event->credentials['email'] ?? null,
        ]));
    }
}
