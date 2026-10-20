<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Foundation\Application;

class IntegrationApiReference
{
    public const SESSION_ONLY_EXTENSION = 'hievents-session-only';

    private const RELATIVE_PATH = 'resources/api-reference/openapi.json';

    private const HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    private const EXCLUDED_PATH_PREFIXES = ['/auth/', '/public/webhooks/', '/public/box-offices/'];

    private const SCHEMA_REF_PATTERN = '~"#/components/schemas/([^"]+)"~';

    public function __construct(
        private readonly Application $app,
    ) {}

    public function path(): string
    {
        return $this->app->basePath(self::RELATIVE_PATH);
    }

    public function generate(): string
    {
        $document = json_decode(
            json_encode($this->app->make(Generator::class)(Scramble::getGeneratorConfig('default')), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        unset($document['servers'], $document['info']['version']);

        $document['paths'] = array_filter(
            array_map($this->withoutSessionOnlyOperations(...), $document['paths']),
            static fn (array $pathItem, string $path) => $pathItem !== [] && ! self::isExcludedPath($path),
            ARRAY_FILTER_USE_BOTH,
        );

        $document = $this->withReachableSchemasOnly($document);

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return preg_replace_callback('/^(?: {4})+/m', static fn (array $indent) => substr($indent[0], 0, intdiv(strlen($indent[0]), 2)), $json)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function published(): array
    {
        $document = json_decode((string) file_get_contents($this->path()), true, flags: JSON_THROW_ON_ERROR);
        $document['info']['version'] = trim((string) file_get_contents($this->app->basePath('VERSION')));

        return $document;
    }

    private static function isExcludedPath(string $path): bool
    {
        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function withReachableSchemasOnly(array $document): array
    {
        $schemas = $document['components']['schemas'] ?? [];
        $pending = self::schemaRefs([$document['paths'], array_diff_key($document['components'] ?? [], ['schemas' => true])]);
        $reachable = [];

        while ($pending !== []) {
            $name = array_pop($pending);

            if (isset($reachable[$name]) || ! isset($schemas[$name])) {
                continue;
            }

            $reachable[$name] = true;
            array_push($pending, ...self::schemaRefs($schemas[$name]));
        }

        $document['components']['schemas'] = array_intersect_key($schemas, $reachable);

        return $document;
    }

    /**
     * @return string[]
     */
    private static function schemaRefs(mixed $node): array
    {
        preg_match_all(self::SCHEMA_REF_PATTERN, json_encode($node, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $matches);

        return $matches[1];
    }

    /**
     * @param  array<string, mixed>  $pathItem
     * @return array<string, mixed>
     */
    private function withoutSessionOnlyOperations(array $pathItem): array
    {
        $operations = array_filter(
            $pathItem,
            static fn ($operation, string $key) => in_array($key, self::HTTP_METHODS, true)
                && ! ($operation['x-'.self::SESSION_ONLY_EXTENSION] ?? false),
            ARRAY_FILTER_USE_BOTH,
        );

        return $operations === [] ? [] : $operations + array_diff_key($pathItem, array_flip(self::HTTP_METHODS));
    }
}
