<?php

namespace Tests\Unit\Services\Application\Handlers\Location;

use Exception;
use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Location\UpdateLocationHandler;
use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateLocationHandlerTest extends TestCase
{
    private LocationRepositoryInterface|MockInterface $locationRepository;

    private LocationDataSanitizer|MockInterface $sanitizer;

    private UpdateLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $this->sanitizer = Mockery::mock(LocationDataSanitizer::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpdateLocationHandler(
            $this->locationRepository,
            $this->sanitizer,
            $databaseManager,
        );
    }

    public function test_throws_when_location_not_found_for_organizer_and_account(): void
    {
        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                LocationDomainObjectAbstract::ID => 1,
                LocationDomainObjectAbstract::ORGANIZER_ID => 10,
                LocationDomainObjectAbstract::ACCOUNT_ID => 5,
            ])
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle(1, $this->makeDto());
    }

    public function test_throws_conflict_when_place_is_used_by_another_location(): void
    {
        $this->expectLocationLookup($this->makeLocation(id: 1));
        $this->expectConflictLookup('place_1', $this->makeLocation(id: 2));

        $this->locationRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);
        $this->handler->handle(1, $this->makeDto());
    }

    public function test_updates_with_cached_raw_response(): void
    {
        $location = $this->makeLocation(id: 1);
        $this->expectLocationLookup($location);
        $this->expectConflictLookup('place_1', $location);
        $this->stubSanitizer();
        $this->sanitizer
            ->shouldReceive('cachedRawProviderResponse')
            ->with('google', 'place_1')
            ->andReturn(['fresh' => true]);

        $capturedAttributes = $this->expectUpdate();

        $this->handler->handle(1, $this->makeDto());
        $this->assertSame(['fresh' => true], $capturedAttributes()[LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE]);
    }

    public function test_preserves_existing_raw_response_when_place_unchanged_and_cache_empty(): void
    {
        $location = $this->makeLocation(id: 1, raw: ['seed' => true]);
        $this->expectLocationLookup($location);
        $this->expectConflictLookup('place_1', $location);
        $this->stubSanitizer();
        $this->sanitizer->shouldReceive('cachedRawProviderResponse')->andReturn(null);

        $capturedAttributes = $this->expectUpdate();

        $this->handler->handle(1, $this->makeDto());
        $this->assertSame(['seed' => true], $capturedAttributes()[LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE]);
    }

    public function test_clears_raw_response_when_place_changes_and_cache_empty(): void
    {
        $location = $this->makeLocation(id: 1, placeId: 'place_1');
        $this->expectLocationLookup($location);
        $this->expectConflictLookup('place_2', null);
        $this->stubSanitizer();
        $this->sanitizer->shouldReceive('cachedRawProviderResponse')->andReturn(null);

        $capturedAttributes = $this->expectUpdate();

        $this->handler->handle(1, $this->makeDto(placeId: 'place_2'));
        $this->assertNull($capturedAttributes()[LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE]);
    }

    public function test_wraps_unique_violation_in_conflict_exception(): void
    {
        $location = $this->makeLocation(id: 1);
        $this->expectLocationLookup($location);
        $this->expectConflictLookup('place_1', $location);
        $this->stubSanitizer();
        $this->sanitizer->shouldReceive('cachedRawProviderResponse')->andReturn(null);

        $this->locationRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'pgsql',
                'update "locations" ...',
                [],
                new Exception('duplicate key value violates unique constraint "locations_provider_place_unique"'),
            ));

        $this->expectException(ResourceConflictException::class);
        $this->handler->handle(1, $this->makeDto());
    }

    private function makeDto(?string $provider = 'google', ?string $placeId = 'place_1'): UpsertLocationDTO
    {
        return new UpsertLocationDTO(
            organizer_id: 10,
            account_id: 5,
            name: 'The Venue',
            structured_address: new AddressDTO(city: 'Dublin', country: 'IE'),
            latitude: 53.3,
            longitude: -6.2,
            provider: $provider,
            provider_place_id: $placeId,
        );
    }

    private function makeLocation(
        int $id,
        ?string $provider = 'google',
        ?string $placeId = 'place_1',
        ?array $raw = null,
    ): LocationDomainObject|MockInterface {
        $location = Mockery::mock(LocationDomainObject::class);
        $location->shouldReceive('getId')->andReturn($id);
        $location->shouldReceive('getProvider')->andReturn($provider);
        $location->shouldReceive('getProviderPlaceId')->andReturn($placeId);
        $location->shouldReceive('getRawProviderResponse')->andReturn($raw);

        return $location;
    }

    private function expectLocationLookup(LocationDomainObject $location): void
    {
        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                LocationDomainObjectAbstract::ID => 1,
                LocationDomainObjectAbstract::ORGANIZER_ID => 10,
                LocationDomainObjectAbstract::ACCOUNT_ID => 5,
            ])
            ->andReturn($location);
    }

    private function expectConflictLookup(string $placeId, ?LocationDomainObject $result): void
    {
        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                LocationDomainObjectAbstract::ORGANIZER_ID => 10,
                LocationDomainObjectAbstract::ACCOUNT_ID => 5,
                LocationDomainObjectAbstract::PROVIDER => 'google',
                LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $placeId,
            ])
            ->andReturn($result);
    }

    private function expectUpdate(): callable
    {
        $captured = null;
        $updated = Mockery::mock(LocationDomainObject::class);
        $this->locationRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(function (array $attributes) use (&$captured) {
                $captured = $attributes;

                return true;
            }))
            ->andReturn($updated);

        return static function () use (&$captured) {
            return $captured;
        };
    }

    private function stubSanitizer(): void
    {
        $this->sanitizer->shouldReceive('sanitizeText')->andReturnUsing(fn (?string $value) => $value);
        $this->sanitizer->shouldReceive('sanitizeAddress')->andReturnUsing(fn (array $address) => $address);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
