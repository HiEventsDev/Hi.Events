<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Webhook\UpsertWebhookRequest;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\DTO\EditWebhookDTO;
use HiEvents\Services\Application\Handlers\Webhook\EditWebhookHandler;
use Illuminate\Http\JsonResponse;

class EditWebhookAction extends BaseAction
{
    public function __construct(
        private readonly EditWebhookHandler $editWebhookHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $webhookId, UpsertWebhookRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $webhook = $this->editWebhookHandler->handle(
            new EditWebhookDTO(
                webhookId: $webhookId,
                url: $request->validated('url'),
                eventTypes: $request->validated('event_types'),
                eventId: $eventId,
                userId: $this->getAuthenticatedUser()->getId(),
                accountId: $this->getAuthenticatedAccountId(),
                status: WebhookStatus::fromName($request->validated('status')),
            )
        );

        return $this->resourceResponse(
            resource: WebhookResource::class,
            data: $webhook
        );
    }
}
