<?php

namespace App\Providers;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::listen(Login::class, fn (Login $event) => AuditLog::record('login', $event->user));
        Event::listen(Failed::class, fn (Failed $event) => AuditLog::record('login_failed', $event->user, [
            'email' => $event->credentials['email'] ?? null,
        ]));
    }
}
