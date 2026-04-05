<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Event\HybridEventService;
use Illuminate\Http\JsonResponse;

class GetEventConnectionDetailsPublicAction extends BaseAction
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly HybridEventService $hybridEventService,
    )
    {
    }

    public function __invoke(int $eventId, int $productId): JsonResponse
    {
        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);

        $product = $this->productRepository->findFirstWhere([
            'id' => $productId,
            'event_id' => $eventId,
        ]);

        $connectionDetails = $this->hybridEventService->getAttendeeConnectionDetails(
            $product,
            $eventSettings,
        );

        return $this->jsonResponse($connectionDetails);
    }
}
