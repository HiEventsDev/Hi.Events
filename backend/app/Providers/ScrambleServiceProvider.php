<?php

declare(strict_types=1);

namespace HiEvents\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ScrambleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MiddlewareAuthSecurityStrategy::class,
            static fn () => new MiddlewareAuthSecurityStrategy(
                scheme: SecurityScheme::http('bearer', 'JWT'),
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

                return str_starts_with($route->getActionName(), 'HiEvents\\Http\\Actions\\');
            })
            ->withOperationTransformers(static function (Operation $operation, RouteInfo $routeInfo): void {
                $tag = Str::of((string) $routeInfo->className())
                    ->after('Http\\Actions\\')
                    ->beforeLast('\\')
                    ->replace('\\', ' / ');

                if ($tag->isNotEmpty()) {
                    $operation->tags = [$tag->toString()];
                }

                if ($operation->summary === '') {
                    $operation->summary(self::summaryFromActionName(class_basename((string) $routeInfo->className())));
                }

                if (str_starts_with($routeInfo->route->uri(), 'public/') && ! str_contains($operation->summary, '(public)')) {
                    $operation->summary($operation->summary.' (public)');
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
