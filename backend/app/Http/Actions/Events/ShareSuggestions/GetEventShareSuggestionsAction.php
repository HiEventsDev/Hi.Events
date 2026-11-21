<?php

namespace HiEvents\Http\Actions\Events\ShareSuggestions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventShareSuggestionsResource;
use HiEvents\Services\Application\Handlers\Event\ShareSuggestion\GetEventShareSuggestionsHandler;
use Illuminate\Http\JsonResponse;

class GetEventShareSuggestionsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventShareSuggestionsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            EventShareSuggestionsResource::class,
            $this->handler->handle($eventId, $this->getAuthenticatedAccountId()),
        );
    }
}
