<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()), Limit::perMinute(30)->by($request->ip())]);
        RateLimiter::for('enroll', fn (Request $request) => Limit::perHour(20)->by($request->ip()));
        RateLimiter::for('enroll-status', fn (Request $request) => [
            Limit::perMinute(30)->by('enrollment:'.hash('sha256', $request->bearerToken() ?? $request->ip())),
            Limit::perMinute(600)->by('enrollment-ip:'.$request->ip()),
        ]);
        RateLimiter::for('telemetry', fn (Request $request) => Limit::perMinute(120)->by('device:'.($request->user()?->id ?? $request->ip())));
    }
}
