<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases;

use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\ProductPurchaseStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\GetProductPurchasesDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetProductPurchasesHandler
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ProductPurchaseProductGuard $productGuard,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(GetProductPurchasesDTO $dto): LengthAwarePaginator
    {
        $this->productGuard->assertProductBelongsToEvent($dto->eventId, $dto->productId);

        $params = $dto->queryParams;

        return $this->orderItemRepository->findProductPurchases(
            filter: new ProductPurchaseFilterDTO(
                eventId: $dto->eventId,
                productId: $dto->productId,
                eventOccurrenceId: $this->getOccurrenceId($params),
                statuses: $this->getFilterValues($params, 'status', ProductPurchaseStatus::valuesArray()),
                refundStatuses: $this->getFilterValues($params, 'refund_status', array_column(OrderRefundStatus::cases(), 'name')),
                query: $params->query,
            ),
            page: max(1, (int) $params->page),
            perPage: min(self::MAX_PER_PAGE, max(1, (int) $params->per_page)),
        );
    }

    private function getOccurrenceId(QueryParamsDTO $params): ?int
    {
        $value = $params->filter_fields?->firstWhere('field', 'event_occurrence_id')?->value;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  string[]  $allowedValues
     * @return string[]
     */
    private function getFilterValues(QueryParamsDTO $params, string $field, array $allowedValues): array
    {
        /** @var FilterFieldDTO|null $filterField */
        $filterField = $params->filter_fields?->firstWhere('field', $field);

        if ($filterField === null) {
            return [];
        }

        return array_values(array_intersect($allowedValues, explode(',', (string) $filterField->value)));
    }
}
