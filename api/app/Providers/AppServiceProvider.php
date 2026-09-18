<?php

namespace App\Providers;

use App\Services\CbsOdataClient;
use App\Services\PdokClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdokClient::class, fn () => PdokClient::fromConfig());
        $this->app->bind(CbsOdataClient::class, fn () => CbsOdataClient::fromConfig());
    }

    public function boot(): void
    {
        RateLimiter::for('incidents', function (Request $request) {
            return Limit::perHour((int) config('veiligonderweg.incidents.rate_limit_per_hour'))
                ->by('incidents:'.($request->user()?->id ?: $request->ip()))
                ->response(fn () => response()->json(['message' => 'Te veel meldingen. Probeer het later opnieuw.'], 429));
        });

        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(30)->by('votes:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by('auth:'.$request->ip()));
    }
}
