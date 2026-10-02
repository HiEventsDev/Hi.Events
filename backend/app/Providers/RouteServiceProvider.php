<?php

namespace HiEvents\Providers;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('app.api_rate_limit_per_minute'))
                ->by($this->boxOfficeSessionKey($request) ?? ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(10)->by('auth-login|'.$this->normalisedEmail($request));
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            return Limit::perHour(5)->by('auth-forgot-password|'.$this->normalisedEmail($request));
        });

        RateLimiter::for('self-service-email', function (Request $request) {
            return Limit::perHour(20)->by($request->route('order_short_id') ?? $request->ip());
        });

        RateLimiter::for('self-service-edit', function (Request $request) {
            return Limit::perHour(20)->by($request->route('order_short_id') ?? $request->ip());
        });

        RateLimiter::for('box-office-session', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->route('box_office_short_id').'|'.$request->ip()),
                Limit::perMinute(60)->by($request->route('box_office_short_id')),
            ];
        });

        RateLimiter::for('public-order-create', function (Request $request) {
            return Limit::perMinute(config('app.public_order_rate_limit_per_minute'))
                ->by('public-order-create|'.$request->ip());
        });

        RateLimiter::for('public-promo-code', function (Request $request) {
            return Limit::perMinute(config('app.public_promo_code_rate_limit_per_minute'))
                ->by('public-promo-code|'.$request->ip());
        });

        RateLimiter::for('box-office-email', function (Request $request) {
            return Limit::perHour(60)->by($request->route('box_office_short_id'));
        });

        $this->routes(function () {
            Route::middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    private function boxOfficeSessionKey(Request $request): ?string
    {
        $token = $request->header(AuthenticateBoxOfficeSession::SESSION_HEADER);

        if (! is_string($token) || app(BoxOfficeSessionService::class)->resolve($token) === null) {
            return null;
        }

        return 'box-office-session|'.hash('sha256', $token);
    }

    private function normalisedEmail(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? strtolower(trim($email)) : '';
    }
}
