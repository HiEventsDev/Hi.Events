<?php

namespace Tests\Unit\Services\Application\Handlers\Location;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\GetLocationsHandler;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class GetLocationsHandlerTest extends TestCase
{
    public function test_delegates_to_repository_with_tenant_scope_and_params(): void
    {
        $params = new QueryParamsDTO(page: 2, per_page: 20, query: 'fillmore');
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $locationRepository
            ->shouldReceive('findByOrganizerId')
            ->once()
            ->with(10, 5, $params)
            ->andReturn($paginator);

        $handler = new GetLocationsHandler($locationRepository);

        $this->assertSame($paginator, $handler->handle(10, 5, $params));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
