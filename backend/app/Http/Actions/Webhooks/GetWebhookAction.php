<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhookHandler;
use Illuminate\Http\JsonResponse;

class GetWebhookAction extends BaseAction
{
    public function __construct(
        private readonly GetWebhookHandler $getWebhookHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $webhookId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $webhook = $this->getWebhookHandler->handle(
            eventId: $eventId,
            webhookId: $webhookId
        );

        return $this->resourceResponse(
            resource: WebhookResource::class,
            data: $webhook
        );
    }
}
