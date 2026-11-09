<?php

namespace HiEvents\Mcp\Routing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use JsonException;

class ApiRouteDispatcher
{
    private const JSON = 'application/json';

    private const MULTIPART = 'multipart/form-data';

    public function __construct(
        private readonly Application $app,
        private readonly Kernel $kernel,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    /**
     * @param  class-string  $action
     */
    public function get(string $action, array $routeParameters = [], array $query = []): ApiRouteResponseDTO
    {
        return $this->dispatch('GET', $action, $routeParameters, $query, [], null, self::JSON);
    }

    /**
     * @param  class-string  $action
     *
     * @throws JsonException
     */
    public function send(string $method, string $action, array $routeParameters = [], array $body = []): ApiRouteResponseDTO
    {
        return $this->dispatch($method, $action, $routeParameters, [], [], json_encode($body, JSON_THROW_ON_ERROR), self::JSON);
    }

    /**
     * @param  class-string  $action
     * @param  array<string, UploadedFile>  $files
     */
    public function upload(string $action, array $routeParameters, array $fields, array $files): ApiRouteResponseDTO
    {
        return $this->dispatch('POST', $action, $routeParameters, $fields, $files, null, self::MULTIPART);
    }

    private function dispatch(
        string $method,
        string $action,
        array $routeParameters,
        array $parameters,
        array $files,
        ?string $content,
        string $contentType,
    ): ApiRouteResponseDTO {
        $outerRequest = $this->app->make('request');

        $innerRequest = Request::create(
            uri: $this->pathTo($action, $routeParameters),
            method: $method,
            parameters: $parameters,
            files: $files,
            server: $this->innerServerVariables($outerRequest, $contentType),
            content: $content,
        );

        try {
            $response = $this->kernel->handle($innerRequest);
        } finally {
            $this->app->instance('request', $outerRequest);
            Facade::clearResolvedInstance('request');
        }

        $body = json_decode((string) $response->getContent(), true);

        return new ApiRouteResponseDTO(
            status: $response->getStatusCode(),
            body: is_array($body) ? $body : [],
        );
    }

    private function pathTo(string $action, array $routeParameters): string
    {
        $root = rtrim($this->urlGenerator->to('/'), '/');

        return '/'.ltrim(Str::after($this->urlGenerator->action($action, $routeParameters), $root), '/');
    }

    private function innerServerVariables(Request $outerRequest, string $contentType): array
    {
        $server = $outerRequest->server->all();

        unset($server['CONTENT_LENGTH'], $server['HTTP_CONTENT_LENGTH']);

        foreach (array_keys($server) as $key) {
            if (str_starts_with($key, 'HTTP_MCP_')) {
                unset($server[$key]);
            }
        }

        $server['CONTENT_TYPE'] = $contentType;
        $server['HTTP_ACCEPT'] = self::JSON;

        return $server;
    }
}
