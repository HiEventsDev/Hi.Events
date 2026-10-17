<?php

declare(strict_types=1);

namespace HiEvents\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use HiEvents\OpenApi\IntegrationApiReference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ScrambleServiceProvider extends ServiceProvider
{
    private const LICENSING_NAMESPACE = 'HiEvents\\Enterprise\\Licensing\\';

    private const ACTION_NAMESPACE_PATTERN = '/^HiEvents\\\\(Enterprise\\\\(\w+)\\\\)?Http\\\\Actions\\\\/';

    public function register(): void
    {
        $this->app->bind(
            MiddlewareAuthSecurityStrategy::class,
            static fn () => new MiddlewareAuthSecurityStrategy(
                scheme: SecurityScheme::http('bearer')->setDescription('An API key (`hie_…`) or a session token from `POST /auth/login`.'),
            ),
        );
    }

    public function boot(): void
    {
        Gate::define(
            'viewApiDocs',
            static fn (?Authenticatable $user): bool => (bool) config('app.api_docs_enabled'),
        );

        Scramble::configure()
            ->routes(static function (Route $route): bool {
                if (Str::is(['mail-test', '*sitemap*', 'admin', 'admin/*'], $route->uri())) {
                    return false;
                }

                if (str_starts_with($route->getActionName(), self::LICENSING_NAMESPACE)) {
                    return false;
                }

                return preg_match(self::ACTION_NAMESPACE_PATTERN, $route->getActionName()) === 1;
            })
            ->withOperationTransformers(static function (Operation $operation, RouteInfo $routeInfo): void {
                $tag = Str::of((string) $routeInfo->className())
                    ->replaceMatches(self::ACTION_NAMESPACE_PATTERN, '${2}\\\\')
                    ->ltrim('\\')
                    ->beforeLast('\\')
                    ->explode('\\')
                    ->filter()
                    ->map(static fn (string $segment) => Str::headline($segment))
                    ->implode(' / ');

                if ($tag !== '') {
                    $operation->tags = [$tag];
                }

                if ($operation->summary === '') {
                    $operation->summary(self::summaryFromActionName(class_basename((string) $routeInfo->className())));
                }

                if (str_starts_with($routeInfo->route->uri(), 'public/') && ! str_contains($operation->summary, '(public)')) {
                    $operation->summary($operation->summary.' (public)');
                }

                if (in_array('auth:api', $routeInfo->route->gatherMiddleware(), true)) {
                    $operation->description(trim($operation->description."\n\n".'Not available to API keys: sign in to call this endpoint.'));
                    $operation->setExtensionProperty(IntegrationApiReference::SESSION_ONLY_EXTENSION, true);
                }
            });
    }

    private static function summaryFromActionName(string $actionName): string
    {
        return Str::of($actionName)->headline()->explode(' ')
            ->reject(static fn (string $word): bool => in_array($word, ['Action', 'Public', ''], true))
            ->implode(' ');
    }
}
