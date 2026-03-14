<?php

namespace HiEvents\Http\Actions\Organizers\Webhooks;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\WebhookLogDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Webhook\WebhookLogResource;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhookLogsHandler;
use Illuminate\Http\JsonResponse;

class GetOrganizerWebhookLogsAction extends BaseAction
{
    public function __construct(
        private readonly GetWebhookLogsHandler $getWebhookLogsHandler,
    )
    {
    }

    public function __invoke(int $organizerId, int $webhookId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $webhookLogs = $this->getWebhookLogsHandler->handle(
            webhookId: $webhookId,
            accountId: $this->getAuthenticatedAccountId(),
            eventId: null,
            organizerId: $organizerId,
        );

        $webhookLogs = $webhookLogs->sortBy(function (WebhookLogDomainObject $webhookLog) {
            return $webhookLog->getId();
        }, SORT_REGULAR, true);

        return $this->resourceResponse(
            resource: WebhookLogResource::class,
            data: $webhookLogs
        );
    }
}
