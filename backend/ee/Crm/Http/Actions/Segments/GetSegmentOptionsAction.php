<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Segments;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentOptionsQueryDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\GetSegmentOptionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetSegmentOptionsAction extends BaseCrmAction
{
    private const MAX_SELECTED_IDS = 50;

    private const MAX_QUERY_LENGTH = 100;

    public function __construct(
        private readonly GetSegmentOptionsHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse($this->handler->handle($organizerId, new SegmentOptionsQueryDTO(
            event_query: $this->searchTerm($request, 'event_query'),
            event_ids: $this->ids($request, 'event_ids'),
            with_products: $request->boolean('with_products'),
            product_query: $this->searchTerm($request, 'product_query'),
            product_ids: $this->ids($request, 'product_ids'),
        )), wrapInData: true);
    }

    private function searchTerm(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, self::MAX_QUERY_LENGTH);
    }

    /**
     * @return int[]
     */
    private function ids(Request $request, string $key): array
    {
        return array_slice(array_values(array_filter(
            array_map('intval', array_filter((array) $request->query($key, []), 'is_scalar')),
            static fn (int $id) => $id > 0,
        )), 0, self::MAX_SELECTED_IDS);
    }
}
