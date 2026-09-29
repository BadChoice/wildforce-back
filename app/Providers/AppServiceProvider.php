<?php

namespace App\Providers;

use App\Contracts\AppleAuthentication;
use App\Contracts\GoogleAuthentication;
use App\Models\User;
use App\Services\Apple\Auth\AppleAuthenticator;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Google\Auth\GoogleAuthenticator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AppleAuthentication::class, AppleAuthenticator::class);
        $this->app->bind(GoogleAuthentication::class, GoogleAuthenticator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::define('viewDashboard', fn (User $user): bool => $user->isAdmin());

        $this->app->singleton(ExerciseCatalog::class, function () {
            return new ExerciseCatalog;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
