<?php

namespace Tests\Unit\Services\Application\Handlers\SeatMap;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\CreateSeatMapHandler;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DeleteSeatMapHandler;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\UpsertSeatMapDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetSeatMapHandler;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateSeatMapHandler;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SeatMapHandlersTest extends TestCase
{
    private SeatMapRepositoryInterface|MockInterface $repository;

    private SeatMapLayoutValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SeatMapRepositoryInterface::class);
        $this->validator = new SeatMapLayoutValidator(new Repository(['app' => ['seat_map_max_seats' => 3000]]));
    }

    public function test_create_stores_the_canonical_layout_and_seat_count(): void
    {
        $this->repository->shouldReceive('create')->once()
            ->with(Mockery::on(fn (array $attributes) => $attributes[SeatMapDomainObjectAbstract::NAME] === 'Club night'
                && $attributes[SeatMapDomainObjectAbstract::SEAT_COUNT] === 48
                && $attributes[SeatMapDomainObjectAbstract::ORGANIZER_ID] === 3
                && $attributes[SeatMapDomainObjectAbstract::ACCOUNT_ID] === 9
                && ! array_key_exists('injected', $attributes[SeatMapDomainObjectAbstract::LAYOUT])))
            ->andReturn(new SeatMapDomainObject);

        $layout = $this->fixture('club');
        $layout['injected'] = true;

        (new CreateSeatMapHandler($this->repository, $this->validator, Mockery::spy(LicensedFeatureUsageService::class)))
            ->handle($this->dto('<i>Club night</i>', $layout));
    }

    public function test_create_rejects_an_invalid_layout_without_writing(): void
    {
        $this->repository->shouldNotReceive('create');
        $this->expectException(InvalidSeatMapLayoutException::class);

        (new CreateSeatMapHandler($this->repository, $this->validator, Mockery::spy(LicensedFeatureUsageService::class)))
            ->handle($this->dto('Broken', ['schema' => 1, 'bands' => [], 'areas' => []]));
    }

    public function test_update_bumps_the_version(): void
    {
        $this->expectLookup((new SeatMapDomainObject)->setId(5)->setVersion(4));
        $this->repository->shouldReceive('updateWhere')->once()
            ->with(
                Mockery::on(fn (array $attributes) => $attributes[SeatMapDomainObjectAbstract::VERSION] === 5
                    && $attributes[SeatMapDomainObjectAbstract::SEAT_COUNT] === 0),
                [SeatMapDomainObjectAbstract::ID => 5, SeatMapDomainObjectAbstract::VERSION => 4],
            )
            ->andReturn(1);
        $this->repository->shouldReceive('findById')->once()->with(5)->andReturn(new SeatMapDomainObject);

        (new UpdateSeatMapHandler($this->repository, $this->validator, new GetSeatMapHandler($this->repository)))
            ->handle(5, $this->dto('Renamed', $this->fixture('empty')));
    }

    public function test_update_is_refused_when_another_save_landed_first(): void
    {
        $this->expectLookup((new SeatMapDomainObject)->setId(5)->setVersion(4));
        $this->repository->shouldReceive('updateWhere')->once()->andReturn(0);
        $this->repository->shouldNotReceive('findById');
        $this->expectException(SeatMapChangeConflictException::class);

        (new UpdateSeatMapHandler($this->repository, $this->validator, new GetSeatMapHandler($this->repository)))
            ->handle(5, $this->dto('Renamed', $this->fixture('empty')));
    }

    public function test_update_of_a_map_outside_the_organizer_is_not_found(): void
    {
        $this->expectLookup(null);
        $this->repository->shouldNotReceive('updateFromArray');
        $this->expectException(ResourceNotFoundException::class);

        (new UpdateSeatMapHandler($this->repository, $this->validator, new GetSeatMapHandler($this->repository)))
            ->handle(5, $this->dto('Renamed', $this->fixture('empty')));
    }

    public function test_delete_removes_only_a_map_owned_by_the_organizer(): void
    {
        $this->expectLookup((new SeatMapDomainObject)->setId(5));
        $this->repository->shouldReceive('deleteById')->once()->with(5)->andReturn(true);

        (new DeleteSeatMapHandler($this->repository, new GetSeatMapHandler($this->repository), Mockery::spy(LicensedFeatureUsageService::class)))->handle(5, 3, 9);
    }

    public function test_delete_of_a_map_outside_the_organizer_is_not_found(): void
    {
        $this->expectLookup(null);
        $this->repository->shouldNotReceive('deleteById');
        $this->expectException(ResourceNotFoundException::class);

        (new DeleteSeatMapHandler($this->repository, new GetSeatMapHandler($this->repository), Mockery::spy(LicensedFeatureUsageService::class)))->handle(5, 3, 9);
    }

    private function expectLookup(?SeatMapDomainObject $result): void
    {
        $this->repository->shouldReceive('findFirstWhere')->once()
            ->with([
                SeatMapDomainObjectAbstract::ID => 5,
                SeatMapDomainObjectAbstract::ORGANIZER_ID => 3,
                SeatMapDomainObjectAbstract::ACCOUNT_ID => 9,
            ])
            ->andReturn($result);
    }

    private function dto(string $name, array $layout): UpsertSeatMapDTO
    {
        return new UpsertSeatMapDTO(organizer_id: 3, account_id: 9, name: $name, layout: $layout);
    }

    private function fixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
