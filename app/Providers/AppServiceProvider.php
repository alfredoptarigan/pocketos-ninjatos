<?php

namespace App\Providers;

use App\Http\Middleware\GameEntry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

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
        $this->configureDefaults();

        // Fights, searches and draws per player, so a script cannot farm them.
        // Game pages show "/" in the address bar, like the original client (GameEntry).
        Inertia::resolveUrlUsing(fn (Request $request) => in_array(GameEntry::class, $request->route()?->gatherMiddleware() ?? [], true)
            ? '/'
            : Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/'));

        RateLimiter::for('game', fn (Request $request) => Limit::perMinute(config('game.actions_per_minute'))->by($request->user()?->id ?: $request->ip()));
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
