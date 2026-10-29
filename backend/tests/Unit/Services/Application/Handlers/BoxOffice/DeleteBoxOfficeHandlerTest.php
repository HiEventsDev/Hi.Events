<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DeleteBoxOfficeHandler;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteBoxOfficeHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeRepositoryInterface $repository;

    private DeleteBoxOfficeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->handler = new DeleteBoxOfficeHandler($this->repository, Mockery::spy(LicensedFeatureUsageService::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refuses_to_delete_system_default(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturn(
            (new BoxOfficeDomainObject)->setId(1)->setIsSystemDefault(true),
        );
        $this->repository->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(eventId: 7, boxOfficeId: 1);
    }

    public function test_throws_not_found_for_unknown_box_office(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(eventId: 7, boxOfficeId: 1);
    }

    public function test_deletes_non_default_box_office(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturn(
            (new BoxOfficeDomainObject)->setId(2)->setIsSystemDefault(false),
        );
        $this->repository->shouldReceive('deleteWhere')->once()->with(['id' => 2, 'event_id' => 7]);

        $this->handler->handle(eventId: 7, boxOfficeId: 2);

        $this->assertTrue(true);
    }
}
