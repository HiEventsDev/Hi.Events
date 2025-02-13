<?php

namespace HiEvents\Http\Actions\Webhooks;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Webhook\DeleteWebhookHandler;
use Illuminate\Http\Response;

class DeleteWebhookAction extends BaseAction
{
    public function __construct(
        private readonly DeleteWebhookHandler $deleteWebhookHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $webhookId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteWebhookHandler->handle(
            $eventId,
            $webhookId,
        );

        return $this->deletedResponse();
    }
}
