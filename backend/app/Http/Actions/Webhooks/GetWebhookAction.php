<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhookHandler;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::VIEW_FINANCIALS)]
class GetWebhookAction extends BaseAction
{
    public function __construct(
        private readonly GetWebhookHandler $getWebhookHandler,
    ) {}

    public function __invoke(int $eventId, int $webhookId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $webhook = $this->getWebhookHandler->handle(
            webhookId: $webhookId,
            accountId: $this->getAuthenticatedAccountId(),
            eventId: $eventId,
        );

        return $this->resourceResponse(
            resource: WebhookResource::class,
            data: $webhook
        );
    }
}
