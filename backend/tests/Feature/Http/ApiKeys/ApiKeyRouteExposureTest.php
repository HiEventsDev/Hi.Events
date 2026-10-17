<?php

namespace Tests\Feature\Http\ApiKeys;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use Tests\TestCase;

class ApiKeyRouteExposureTest extends TestCase
{
    private const SESSION_ONLY_GUARD = 'auth:api';

    private const API_KEY_GUARDS = 'auth:api,sanctum';

    private const SESSION_ONLY_ROUTES = [
        'DELETE accounts/deletion-request',
        'DELETE accounts/{account_id}/sso/connection',
        'DELETE accounts/{account_id}/sso/domains/{domain_id}',
        'DELETE api-keys/{api_key_id}',
        'DELETE organizers/{organizerId}/stripe/connect_accounts/{stripeAccountId}',
        'DELETE organizers/{organizerId}/stripe/terminal/readers/{readerId}',
        'DELETE users/me/sso-identities/{identity_id}',
        'DELETE users/me/two-factor/trusted-devices',
        'DELETE users/me/two-factor/trusted-devices/{device_id}',
        'DELETE users/{user_id}/email-change',
        'DELETE users/{user_id}/invitation',
        'GET accounts/deletion-request',
        'GET accounts/{account_id}/sso',
        'GET accounts/{account_id}/sso/enforcement',
        'GET announcements/active',
        'GET api-keys',
        'GET api-reference',
        'GET auth/logout',
        'GET events/{event_id}/team',
        'GET organizers/{organizerId}/stripe/connect_accounts',
        'GET organizers/{organizerId}/stripe/terminal/readers',
        'GET organizers/{organizer_id}/team',
        'GET users',
        'GET users/me/sso-identities',
        'GET users/me/two-factor',
        'GET users/{user_id}',
        'POST accounts/deletion-request',
        'POST accounts/{account_id}/sso/connection',
        'POST accounts/{account_id}/sso/connection/check',
        'POST accounts/{account_id}/sso/connection/test',
        'POST accounts/{account_id}/sso/domains',
        'POST accounts/{account_id}/sso/domains/{domain_id}/verify',
        'POST announcements/{announcement_id}/dismiss',
        'POST api-keys',
        'POST auth/invitation/{invite_token}/join',
        'POST auth/refresh',
        'POST organizers/{organizerId}/stripe/connect',
        'POST organizers/{organizerId}/stripe/copy_from/{sourceOrganizerId}',
        'POST organizers/{organizerId}/stripe/terminal/readers',
        'POST organizers/{organizerId}/vat-settings',
        'POST users',
        'POST users/me/sso-identities/exchange',
        'POST users/me/sso-identities/{provider}/reauth',
        'POST users/me/sso-identities/{provider}/start',
        'POST users/me/two-factor/confirm',
        'POST users/me/two-factor/disable',
        'POST users/me/two-factor/recovery-codes',
        'POST users/me/two-factor/setup',
        'POST users/{user_id}/confirm-email-with-code',
        'POST users/{user_id}/confirm-email/{resetToken}',
        'POST users/{user_id}/email-change/{changeToken}',
        'POST users/{user_id}/invitation',
        'POST users/{user_id}/resend-email-confirmation',
        'POST users/{user_id}/two-factor/reset',
        'PUT accounts/{account_id?}',
        'PUT accounts/{account_id}/sso/connection',
        'PUT accounts/{account_id}/sso/connection/status',
        'PUT accounts/{account_id}/sso/enforcement',
        'PUT accounts/{account_id}/sso/members/{account_user_id}',
        'PUT accounts/{account_id}/two-factor-requirement',
        'PUT users/me',
        'PUT users/{user_id}',
    ];

    private const PERMISSIONS_KEYS_CANNOT_USE = [
        Permission::MANAGE_TEAM,
        Permission::MANAGE_ACCOUNT,
        Permission::MANAGE_PAYMENTS,
    ];

    public function test_session_only_routes_are_a_deliberate_list(): void
    {
        $sessionOnly = collect($this->authenticatedRoutes())
            ->filter(fn (Route $route) => $this->authGuards($route) === self::SESSION_ONLY_GUARD)
            ->reject(fn (Route $route) => str_starts_with($route->uri(), 'admin/'))
            ->map(fn (Route $route) => $this->describe($route))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(self::SESSION_ONLY_ROUTES, $sessionOnly);
    }

    public function test_admin_routes_never_accept_keys(): void
    {
        foreach ($this->authenticatedRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/')) {
                $this->assertSame(self::SESSION_ONLY_GUARD, $this->authGuards($route), $this->describe($route));
            }
        }
    }

    public function test_every_signed_in_route_uses_a_known_guard(): void
    {
        foreach ($this->authenticatedRoutes() as $route) {
            $this->assertContains($this->authGuards($route), [self::SESSION_ONLY_GUARD, self::API_KEY_GUARDS], $this->describe($route));
        }
    }

    public function test_team_account_and_payment_settings_are_never_reachable_with_a_key(): void
    {
        foreach ($this->authenticatedRoutes() as $route) {
            $controller = $route->getControllerClass();

            if ($controller === null || $this->authGuards($route) !== self::API_KEY_GUARDS) {
                continue;
            }

            $required = collect((new ReflectionClass($controller))->getAttributes(RequiresPermission::class))
                ->flatMap(fn ($attribute) => $attribute->newInstance()->permissions)
                ->all();

            $blocked = array_filter($required, static fn (Permission $permission) => in_array($permission, self::PERMISSIONS_KEYS_CANNOT_USE, true));

            $this->assertSame([], array_values($blocked), $this->describe($route));
        }
    }

    /**
     * @return Route[]
     */
    private function authenticatedRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => $this->authGuards($route) !== null,
        ));
    }

    private function authGuards(Route $route): ?string
    {
        return collect($route->gatherMiddleware())
            ->first(static fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'auth:'));
    }

    private function describe(Route $route): string
    {
        return implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
    }
}
