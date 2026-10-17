<?php

namespace HiEvents\Providers;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
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

        RateLimiter::for('auth-two-factor', function (Request $request) {
            return [
                Limit::perMinute(10)->by('auth-two-factor|'.hash('sha256', (string) $request->input('challenge_token'))),
                Limit::perMinute(30)->by('auth-two-factor-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            return Limit::perHour(5)->by('auth-forgot-password|'.$this->normalisedEmail($request));
        });

        RateLimiter::for('auth-register', function (Request $request) {
            $perHour = (int) config('app.register_rate_limit_per_hour');

            return [
                Limit::perHour($perHour)->by('auth-register|'.$request->ip()),
                Limit::perDay($perHour * 3)->by('auth-register-day|'.$request->ip()),
                Limit::perHour(5)->by('auth-register-email|'.$this->normalisedEmail($request)),
            ];
        });

        RateLimiter::for('auth-sso-start', function (Request $request) {
            return Limit::perMinute(20)->by('auth-sso-start|'.$request->ip());
        });

        RateLimiter::for('auth-sso-exchange', function (Request $request) {
            return Limit::perMinute(20)->by('auth-sso-exchange|'.$request->ip());
        });

        RateLimiter::for('auth-sso-discover', function (Request $request) {
            return [
                Limit::perMinute(10)->by('auth-sso-discover|'.$request->ip()),
                Limit::perHour(60)->by('auth-sso-discover-hour|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-sso-signup-code', function (Request $request) {
            return [
                Limit::perHour(5)->by('auth-sso-signup-code|'.hash('sha256', (string) $request->input('signup_ticket'))),
                Limit::perHour(20)->by('auth-sso-signup-code-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-sso-signup', function (Request $request) {
            $perHour = (int) config('app.register_rate_limit_per_hour');

            return [
                Limit::perHour($perHour)->by('auth-sso-signup|'.$request->ip()),
                Limit::perDay($perHour * 3)->by('auth-sso-signup-day|'.$request->ip()),
                Limit::perMinute(10)->by('auth-sso-signup-ticket|'.hash('sha256', (string) $request->input('signup_ticket'))),
            ];
        });

        RateLimiter::for('user-invite', function (Request $request) {
            return [
                Limit::perMinute(10)->by('user-invite|'.$request->user()?->id),
                Limit::perDay(100)->by('user-invite-day|'.$request->user()?->id),
            ];
        });

        RateLimiter::for('user-email-confirmation', function (Request $request) {
            return Limit::perDay(10)->by('user-email-confirmation|'.$request->user()?->id);
        });

        RateLimiter::for('user-email-confirmation-code', function (Request $request) {
            return Limit::perHour(10)->by('user-email-confirmation-code|'.$request->user()?->id);
        });

        RateLimiter::for('user-update-me', function (Request $request) {
            return Limit::perHour(30)->by('user-update-me|'.$request->user()?->id);
        });

        RateLimiter::for('self-service-email', function (Request $request) {
            if ($request->route('box_office_short_id') !== null) {
                return Limit::perHour(20)->by('self-service-email|'.$this->selfServiceOrderKey($request));
            }

            return [
                Limit::perHour(20)->by('self-service-email|'.$this->selfServiceOrderKey($request)),
                Limit::perHour(60)->by('self-service-email-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('self-service-edit', function (Request $request) {
            return [
                Limit::perHour(20)->by('self-service-edit|'.$this->selfServiceOrderKey($request)),
                Limit::perHour(60)->by('self-service-edit-ip|'.$request->ip()),
            ];
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

        RateLimiter::for('public-payment-intent', function (Request $request) {
            $perHour = (int) config('app.public_payment_intent_rate_limit_per_hour');

            return [
                Limit::perMinute(max(1, intdiv($perHour, 6)))->by('public-payment-intent|'.$request->ip()),
                Limit::perHour($perHour)->by('public-payment-intent-hour|'.$request->ip()),
            ];
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

    private function selfServiceOrderKey(Request $request): string
    {
        $shortId = $request->route('order_short_id');

        if (! is_string($shortId)) {
            return 'ip|'.$request->ip();
        }

        $order = app(OrderRepositoryInterface::class)->findFirstWhere([
            OrderDomainObjectAbstract::SHORT_ID => $shortId,
        ]);

        return $order !== null ? 'order|'.$order->getId() : 'short|'.$shortId;
    }

    private function normalisedEmail(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? strtolower(trim($email)) : '';
    }
}
