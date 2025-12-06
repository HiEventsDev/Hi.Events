<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllOrdersDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllOrdersHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class GetAllOrdersHandlerTest extends TestCase
{
    private OrderRepositoryInterface $repository;
    private GetAllOrdersHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(OrderRepositoryInterface::class);
        $this->handler = new GetAllOrdersHandler($this->repository);
    }

    public function testHandleReturnsPaginatedOrders(): void
    {
        $dto = new GetAllOrdersDTO(
            perPage: 20,
            search: null,
            sortBy: 'created_at',
            sortDirection: 'desc',
        );

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('getAllOrdersForAdmin')
            ->with(null, 20, 'created_at', 'desc')
            ->once()
            ->andReturn($paginator);

        $result = $this->handler->handle($dto);

        $this->assertSame($paginator, $result);
    }

    public function testHandleWithSearchQuery(): void
    {
        $dto = new GetAllOrdersDTO(
            perPage: 10,
            search: 'test@example.com',
            sortBy: 'created_at',
            sortDirection: 'desc',
        );

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('getAllOrdersForAdmin')
            ->with('test@example.com', 10, 'created_at', 'desc')
            ->once()
            ->andReturn($paginator);

        $result = $this->handler->handle($dto);

        $this->assertSame($paginator, $result);
    }

    public function testHandleWithCustomSorting(): void
    {
        $dto = new GetAllOrdersDTO(
            perPage: 25,
            search: null,
            sortBy: 'total_gross',
            sortDirection: 'asc',
        );

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('getAllOrdersForAdmin')
            ->with(null, 25, 'total_gross', 'asc')
            ->once()
            ->andReturn($paginator);

        $result = $this->handler->handle($dto);

        $this->assertSame($paginator, $result);
    }

    public function testHandleWithDefaultValues(): void
    {
        $dto = new GetAllOrdersDTO();

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('getAllOrdersForAdmin')
            ->with(null, 20, 'created_at', 'desc')
            ->once()
            ->andReturn($paginator);

        $result = $this->handler->handle($dto);

        $this->assertSame($paginator, $result);
    }

    public function testHandleWithNameSearch(): void
    {
        $dto = new GetAllOrdersDTO(
            perPage: 20,
            search: 'John Doe',
            sortBy: 'first_name',
            sortDirection: 'asc',
        );

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('getAllOrdersForAdmin')
            ->with('John Doe', 20, 'first_name', 'asc')
            ->once()
            ->andReturn($paginator);

        $result = $this->handler->handle($dto);

        $this->assertSame($paginator, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
