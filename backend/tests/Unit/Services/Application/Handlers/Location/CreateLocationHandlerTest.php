<?php

namespace Tests\Unit\Services\Application\Handlers\Location;

use Exception;
use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\CreateLocationHandler;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateLocationHandlerTest extends TestCase
{
    private LocationRepositoryInterface|MockInterface $locationRepository;

    private LocationDataSanitizer|MockInterface $sanitizer;

    private CreateLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $this->sanitizer = Mockery::mock(LocationDataSanitizer::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new CreateLocationHandler(
            $this->locationRepository,
            $this->sanitizer,
            $databaseManager,
        );
    }

    public function test_reuses_existing_location_for_same_provider_place(): void
    {
        $existing = Mockery::mock(LocationDomainObject::class);

        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                LocationDomainObjectAbstract::ORGANIZER_ID => 10,
                LocationDomainObjectAbstract::ACCOUNT_ID => 5,
                LocationDomainObjectAbstract::PROVIDER => 'google',
                LocationDomainObjectAbstract::PROVIDER_PLACE_ID => 'place_1',
            ])
            ->andReturn($existing);

        $this->locationRepository->shouldNotReceive('create');

        $this->assertSame($existing, $this->handler->handle($this->makeDto()));
    }

    public function test_creates_location_with_sanitized_fields_and_cached_raw_response(): void
    {
        $this->locationRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);
        $this->sanitizer->shouldReceive('sanitizeText')->with('The Venue')->andReturn('Clean Venue');
        $this->sanitizer
            ->shouldReceive('sanitizeAddress')
            ->with(Mockery::type('array'))
            ->andReturn(['city' => 'Dublin', 'country' => 'IE']);
        $this->sanitizer
            ->shouldReceive('cachedRawProviderResponse')
            ->with('google', 'place_1')
            ->andReturn(['id' => 'place_1']);

        $created = Mockery::mock(LocationDomainObject::class);
        $capturedAttributes = null;
        $this->locationRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$capturedAttributes, $created) {
                $capturedAttributes = $attributes;

                return $created;
            });

        $this->assertSame($created, $this->handler->handle($this->makeDto()));
        $this->assertSame('Clean Venue', $capturedAttributes[LocationDomainObjectAbstract::NAME]);
        $this->assertSame(['city' => 'Dublin', 'country' => 'IE'], $capturedAttributes[LocationDomainObjectAbstract::STRUCTURED_ADDRESS]);
        $this->assertSame(['id' => 'place_1'], $capturedAttributes[LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE]);
        $this->assertTrue(str_starts_with($capturedAttributes[LocationDomainObjectAbstract::SHORT_ID], IdHelper::LOCATION_PREFIX));
    }

    public function test_manual_location_skips_reuse_lookup_and_stores_null_raw_response(): void
    {
        $this->locationRepository->shouldNotReceive('findFirstWhere');
        $this->stubSanitizer();
        $this->sanitizer
            ->shouldReceive('cachedRawProviderResponse')
            ->with(null, null)
            ->andReturn(null);

        $capturedAttributes = null;
        $created = Mockery::mock(LocationDomainObject::class);
        $this->locationRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$capturedAttributes, $created) {
                $capturedAttributes = $attributes;

                return $created;
            });

        $this->assertSame($created, $this->handler->handle($this->makeDto(provider: null, placeId: null)));
        $this->assertNull($capturedAttributes[LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE]);
    }

    public function test_returns_existing_location_when_concurrent_create_hits_unique_index(): void
    {
        $existing = Mockery::mock(LocationDomainObject::class);

        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->twice()
            ->andReturn(null, $existing);

        $this->stubSanitizer();
        $this->sanitizer->shouldReceive('cachedRawProviderResponse')->andReturn(null);

        $this->locationRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow($this->makeUniqueViolation());

        $this->assertSame($existing, $this->handler->handle($this->makeDto()));
    }

    public function test_rethrows_unique_violation_when_no_existing_row_matches(): void
    {
        $this->locationRepository->shouldNotReceive('findFirstWhere');
        $this->stubSanitizer();
        $this->sanitizer->shouldReceive('cachedRawProviderResponse')->andReturn(null);

        $this->locationRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow($this->makeUniqueViolation());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->handler->handle($this->makeDto(provider: null, placeId: null));
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

    private function stubSanitizer(): void
    {
        $this->sanitizer->shouldReceive('sanitizeText')->andReturnUsing(fn (?string $value) => $value);
        $this->sanitizer->shouldReceive('sanitizeAddress')->andReturnUsing(fn (array $address) => $address);
    }

    private function makeUniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            'pgsql',
            'insert into "locations" ...',
            [],
            new Exception('duplicate key value violates unique constraint "locations_provider_place_unique"'),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
