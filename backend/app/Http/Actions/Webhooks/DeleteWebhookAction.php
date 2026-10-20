<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Application\Handlers\Webhook\DeleteWebhookHandler;
use Illuminate\Http\Response;

#[RequiresPermission(Permission::VIEW_FINANCIALS)]
class DeleteWebhookAction extends BaseAction
{
    public function __construct(
        private readonly DeleteWebhookHandler $deleteWebhookHandler,
    ) {}

    public function __invoke(int $eventId, int $webhookId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteWebhookHandler->handle(
            webhookId: $webhookId,
            accountId: $this->getAuthenticatedAccountId(),
            eventId: $eventId,
        );

        return $this->deletedResponse();
    }
}
