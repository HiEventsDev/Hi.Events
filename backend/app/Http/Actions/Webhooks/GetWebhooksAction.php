<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhooksHandler;
use Illuminate\Http\JsonResponse;

class GetWebhooksAction extends BaseAction
{
    public function __construct(
        private readonly GetWebhooksHandler $getWebhooksHandler,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $webhooks = $this->getWebhooksHandler->handler(
            accountId: $this->getAuthenticatedAccountId(),
            eventId: $eventId
        );

        return $this->resourceResponse(
            resource: WebhookResource::class,
            data: $webhooks
        );
    }
}
