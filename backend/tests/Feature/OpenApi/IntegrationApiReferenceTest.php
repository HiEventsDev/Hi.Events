<?php

namespace Tests\Feature\OpenApi;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class IntegrationApiReferenceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    public function test_the_committed_reference_matches_the_code(): void
    {
        $result = Process::path(base_path())
            ->timeout(180)
            ->run([PHP_BINARY, '-d', 'memory_limit=1G', 'artisan', 'api-reference:export', '--check']);

        $this->assertTrue($result->successful(), trim($result->output().$result->errorOutput()));
    }

    public function test_signed_in_users_get_the_reference_for_api_key_endpoints_only(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();

        $response = $this->getJson('/api-reference', $this->headersFor($owner, $accountId))->assertOk();

        $paths = $response->json('paths');
        $this->assertArrayHasKey('/events', $paths);
        $this->assertArrayHasKey('/users/me', $paths);
        $this->assertSame(['get'], array_keys($paths['/users/me']));
        $this->assertArrayNotHasKey('/api-keys', $paths);
        $this->assertArrayNotHasKey('/users', $paths);
        $this->assertSame([], array_values(array_filter(array_keys($paths), static fn (string $path) => str_starts_with($path, '/auth/'))));
        $this->assertArrayHasKey('/public/events/{eventId}', $paths);
        $this->assertArrayNotHasKey('/public/instance', $paths);
        $this->assertArrayNotHasKey('/public/compliance', $paths);
        $this->assertArrayNotHasKey('/public/webhooks/stripe', $paths);

        $schemas = $response->json('components.schemas');
        $this->assertArrayNotHasKey('LoginRequest', $schemas);
        $this->assertArrayNotHasKey('CreatedApiKeyResource', $schemas);
        $this->assertArrayNotHasKey('UpsertAccountSsoConnectionRequest', $schemas);
        $this->assertArrayHasKey('EventResource', $schemas);
        $this->assertArrayNotHasKey('servers', $response->json());
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), $response->json('info.version'));
    }

    public function test_empty_schemas_are_objects_not_arrays(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();

        $served = $this->getJson('/api-reference', $this->headersFor($owner, $accountId))->assertOk()->getContent();

        $this->assertSame([], self::emptyArraySchemas(file_get_contents(base_path('resources/api-reference/openapi.json'))));
        $this->assertSame([], self::emptyArraySchemas($served));
        $this->assertStringContainsString('"items":{}', $served);
    }

    public function test_object_and_enum_fields_are_documented_with_their_real_types(): void
    {
        $schemas = json_decode(file_get_contents(base_path('resources/api-reference/openapi.json')), true)['components']['schemas'];

        $this->assertEqualsCanonicalizing(
            array_column(LicensedFeature::cases(), 'value'),
            $schemas['MeResource']['properties']['licence']['properties']['features_in_use']['items']['enum'],
        );

        $widget = $schemas['WidgetResource']['properties'];
        $this->assertSame('object', $widget['settings']['type']);
        $this->assertSame('object', $widget['stats']['properties']['revenue']['type']);
        $this->assertSame('number', $widget['stats']['properties']['revenue']['additionalProperties']['type']);

        $this->assertSame(
            [-1, 1, 2, 3, 4, null],
            $schemas['GenerateOccurrencesRequest']['properties']['recurrence_rule']['properties']['week_position']['enum'],
        );
    }

    public function test_every_operation_has_a_unique_operation_id(): void
    {
        $operationIds = array_column(self::operations(), 'operationId');

        $this->assertCount(count(self::operations()), $operationIds);
        $this->assertSame([], array_keys(array_filter(array_count_values($operationIds), static fn (int $count) => $count > 1)));
        $this->assertContains('events.getEventPublicAction.getPublicEventsEventId', $operationIds);
        $this->assertContains('events.getEventPublicAction.getPublicEventsEventIdProducts', $operationIds);
    }

    public function test_paths_do_not_differ_only_by_parameter_names(): void
    {
        $paths = array_keys(self::committedReference()['paths']);
        $shapes = array_map(static fn (string $path) => preg_replace('/\{[^}]+}/', '{}', $path), $paths);

        $this->assertSame([], array_keys(array_filter(array_count_values($shapes), static fn (int $count) => $count > 1)));
    }

    public function test_every_request_body_is_documented(): void
    {
        $undocumented = array_keys(array_filter(
            self::operations(),
            static fn (array $operation) => str_contains($operation['description'] ?? '', 'Cannot generate request documentation'),
        ));

        $this->assertSame([], $undocumented);
        $this->assertArrayHasKey('requestBody', self::committedReference()['paths']['/events/{eventId}/questions']['post']);
    }

    public function test_tuples_use_json_schema_2020_12_keywords(): void
    {
        $json = file_get_contents(base_path('resources/api-reference/openapi.json'));

        $this->assertStringNotContainsString('"additionalItems"', $json);

        $emptyTuple = self::committedReference()['components']['schemas']['CheckInListResourcePublic']['properties']['event_occurrences']['anyOf'][1];
        $this->assertSame(['type' => 'array', 'items' => false, 'minItems' => 0, 'maxItems' => 0], $emptyTuple);
    }

    public function test_the_reference_needs_a_signed_in_session(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $apiKey = $this->createApiKey($owner, $accountId, ApiKeyAccess::READ_ONLY);

        $this->getJson('/api-reference')->assertUnauthorized();
        $this->getJson('/api-reference', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_NOT_ALLOWED');
    }

    /**
     * @return array<string, mixed>
     */
    private static function committedReference(): array
    {
        return json_decode(file_get_contents(base_path('resources/api-reference/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function operations(): array
    {
        $operations = [];

        foreach (self::committedReference()['paths'] as $path => $pathItem) {
            foreach (array_intersect_key($pathItem, array_flip(['get', 'put', 'post', 'delete', 'patch'])) as $method => $operation) {
                $operations[strtoupper($method).' '.$path] = $operation;
            }
        }

        return $operations;
    }

    /**
     * @return array<string, int>
     */
    private static function emptyArraySchemas(string $json): array
    {
        preg_match_all('/"(items|additionalProperties|properties|schema|not|contains|propertyNames)":\s*\[\]/', $json, $matches);

        return array_count_values($matches[1]);
    }
}
