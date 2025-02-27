<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Webhook\UpsertWebhookRequest;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Services\Application\Handlers\Webhook\CreateWebhookHandler;
use HiEvents\Services\Application\Handlers\Webhook\DTO\CreateWebhookDTO;
use Illuminate\Http\JsonResponse;

class CreateWebhookAction extends BaseAction
{
    public function __construct(
        private readonly CreateWebhookHandler $createWebhookHandler,
    )
    {
    }

    public function __invoke(int $eventId, UpsertWebhookRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $webhook = $this->createWebhookHandler->handle(
            new CreateWebhookDTO(
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
