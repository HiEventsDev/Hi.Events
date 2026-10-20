<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;

class GetEventPublicAction extends BasePublicEventAction
{
    private const MAX_FILTER_IDS = 100;

    public function __construct(
        private readonly GetPublicEventHandler $getPublicEventHandler,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $event = $this->getPublicEventHandler->handle(GetPublicEventDTO::fromArray([
            'eventId' => $eventId,
            'ipAddress' => $this->getClientIp($request),
            'promoCode' => strtolower($request->string('promo_code')),
            'isAuthenticated' => $this->isUserAuthenticated(),
            'eventOccurrenceId' => $request->integer('event_occurrence_id') ?: null,
            'productIds' => $this->parseIdList($request->query('product_ids')),
            'productCategoryIds' => $this->parseIdList($request->query('product_category_ids')),
        ]));

        if (! $this->canUserViewEvent($event)) {
            $this->logger->debug(__('Event with ID :eventId is not live and user is not authenticated', [
                'eventId' => $eventId,
            ]));

            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventResourcePublic::class, $event);
    }

    /**
     * @return int[]
     */
    private function parseIdList(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->filter(static fn ($id) => is_scalar($id) && preg_match('/^\s*\d+\s*$/', (string) $id) === 1)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->take(self::MAX_FILTER_IDS)
            ->values()
            ->all();
    }
}
