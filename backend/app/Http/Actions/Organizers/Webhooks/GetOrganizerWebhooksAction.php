<?php

namespace HiEvents\Http\Actions\Organizers\Webhooks;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhooksHandler;
use Illuminate\Http\JsonResponse;

class GetOrganizerWebhooksAction extends BaseAction
{
    public function __construct(
        private readonly GetWebhooksHandler $getWebhooksHandler,
    )
    {
    }

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $webhooks = $this->getWebhooksHandler->handler(
            accountId: $this->getAuthenticatedAccountId(),
            eventId: null,
            organizerId: $organizerId
        );

        return $this->resourceResponse(
            resource: WebhookResource::class,
            data: $webhooks
        );
    }
}
