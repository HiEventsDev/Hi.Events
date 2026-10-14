<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Product\Purchases;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\GetProductPurchasesDTO;
use HiEvents\Services\Application\Handlers\Product\Purchases\GetProductPurchasesHandler;
use HiEvents\Services\Application\Handlers\Product\Purchases\ProductPurchaseProductGuard;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetProductPurchasesHandlerTest extends TestCase
{
    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private GetProductPurchasesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->handler = new GetProductPurchasesHandler(
            $this->orderItemRepository,
            new ProductPurchaseProductGuard($this->productRepository),
        );
    }

    public function test_translates_filters_and_caps_page_size(): void
    {
        $this->productRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 7, 'event_id' => 3])
            ->andReturn(new ProductDomainObject);

        $paginator = new LengthAwarePaginator([], 0, 100);

        $this->orderItemRepository
            ->shouldReceive('findProductPurchases')
            ->once()
            ->withArgs(function (ProductPurchaseFilterDTO $filter, int $page, int $perPage) {
                return $filter->eventId === 3
                    && $filter->productId === 7
                    && $filter->eventOccurrenceId === 12
                    && $filter->statuses === ['SOLD', 'AWAITING_PAYMENT']
                    && $filter->refundStatuses === ['REFUNDED']
                    && $filter->query === 'ada'
                    && $page === 2
                    && $perPage === 100;
            })
            ->andReturn($paginator);

        $result = $this->handler->handle(new GetProductPurchasesDTO(
            eventId: 3,
            productId: 7,
            queryParams: QueryParamsDTO::fromArray([
                'page' => 2,
                'per_page' => 5000,
                'query' => 'ada',
                'filter_fields' => [
                    'status' => ['in' => 'SOLD,AWAITING_PAYMENT,BOGUS'],
                    'refund_status' => ['in' => 'REFUNDED'],
                    'event_occurrence_id' => ['eq' => '12'],
                ],
            ]),
        ));

        $this->assertSame($paginator, $result);
    }

    public function test_rejects_a_product_from_another_event(): void
    {
        $this->productRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 7, 'event_id' => 3])
            ->andReturnNull();

        $this->orderItemRepository->shouldNotReceive('findProductPurchases');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new GetProductPurchasesDTO(
            eventId: 3,
            productId: 7,
            queryParams: QueryParamsDTO::fromArray([]),
        ));
    }
}
