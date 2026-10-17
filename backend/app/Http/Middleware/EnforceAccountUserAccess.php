<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Exceptions\ApiKeyReadOnlyException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ReadOnlyAccessException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\Admin\Users\StopImpersonationAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccess;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;

class EnforceAccountUserAccess
{
    public function __construct(
        private readonly AuthUserService $authUserService,
        private readonly AccountUserAccessService $accessService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->routeRequiresAuthentication($request) || ! Auth::check() || $this->isAuthPath($request)) {
            return $next($request);
        }

        $controller = $request->route()?->getControllerClass();
        $accountUser = $this->authUserService->getUser()?->getCurrentAccountUser();

        if ($accountUser === null && $this->authUserService->isApiKeyRequest()) {
            throw new UnauthorizedException;
        }

        if ($accountUser === null || $controller === StopImpersonationAction::class) {
            return $next($request);
        }

        if ($accountUser->getStatus() !== UserStatus::ACTIVE->name) {
            $this->authUserService->logout();

            throw new UnauthorizedException(__('Your account is not active.'));
        }

        $access = $this->accessService->forAccountUser($accountUser);

        foreach ($this->requiredPermissions($controller) as $permission) {
            if (! $access->can($permission)) {
                throw new PermissionRequiredException;
            }
        }

        if ($this->isBlockedForReadOnlyUser($request, $access, $controller)) {
            throw new ReadOnlyAccessException;
        }

        if ($this->isBlockedForReadOnlyApiKey($request, $controller)) {
            throw new ApiKeyReadOnlyException;
        }

        return $next($request);
    }

    /**
     * @return Permission[]
     */
    private function requiredPermissions(?string $controller): array
    {
        if ($controller === null || ! class_exists($controller)) {
            return [];
        }

        return collect((new ReflectionClass($controller))->getAttributes(RequiresPermission::class))
            ->flatMap(static fn ($attribute) => $attribute->newInstance()->permissions)
            ->all();
    }

    private function isBlockedForReadOnlyUser(Request $request, AccountUserAccess $access, ?string $controller): bool
    {
        return ! $access->can(Permission::EDIT)
            && ! $request->isMethodSafe()
            && ! $this->isAllowedForReadOnlyUsers($controller);
    }

    private function isBlockedForReadOnlyApiKey(Request $request, ?string $controller): bool
    {
        return ! $this->authUserService->apiKeyCanWrite()
            && ! $request->isMethodSafe()
            && ! $this->isAllowedForReadOnlyUsers($controller);
    }

    private function isAllowedForReadOnlyUsers(?string $controller): bool
    {
        return $controller !== null
            && class_exists($controller)
            && (new ReflectionClass($controller))->getAttributes(AllowedForReadOnlyUsers::class) !== [];
    }

    private function routeRequiresAuthentication(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'auth:'));
    }

    private function isAuthPath(Request $request): bool
    {
        return str_starts_with(trim($request->path(), '/'), 'auth/');
    }
}
