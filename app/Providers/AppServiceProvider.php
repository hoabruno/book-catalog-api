<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Outside production, lazy loading (N+1), silently discarded attributes
        // and access to missing attributes throw instead of passing unnoticed.
        Model::shouldBeStrict(! $production);
        DB::prohibitDestructiveCommands($production);
        Date::use(CarbonImmutable::class);
        Password::defaults(fn () => $production ? Password::min(12)->uncompromised() : Password::min(8));

        if ($production) {
            URL::forceHttps();
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // The API documentation is public on purpose: it is part of the demo.
        Gate::define('viewApiDocs', fn (?User $user): bool => true);
    }
}
