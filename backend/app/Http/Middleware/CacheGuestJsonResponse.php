<?php

namespace HiEvents\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CacheGuestJsonResponse
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function handle(Request $request, Closure $next, int $seconds, string ...$cacheableQueryParams): Response
    {
        if (! $request->isMethod('GET') || Auth::check()) {
            return $next($request);
        }

        $key = $this->cacheKey($request, $cacheableQueryParams);
        $cached = $this->cache->get($key);

        if (is_string($cached)) {
            return JsonResponse::fromJsonString($cached);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() === Response::HTTP_OK) {
            $this->cache->put($key, $response->getContent(), $seconds);
        }

        return $response;
    }

    /**
     * @param  string[]  $cacheableQueryParams
     */
    private function cacheKey(Request $request, array $cacheableQueryParams): string
    {
        $query = array_intersect_key($request->query->all(), array_flip($cacheableQueryParams));
        ksort($query);

        return 'guest_json_response:'.md5(json_encode([$request->path(), $query, App::getLocale()]));
    }
}
