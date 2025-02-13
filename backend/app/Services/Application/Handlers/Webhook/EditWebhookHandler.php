<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use HiEvents\Services\Application\Handlers\Webhook\DTO\EditWebhookDTO;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EditWebhookHandler
{
    public function __construct(
        private readonly WebhookRepositoryInterface $webhookRepository,
        private readonly DatabaseManager            $databaseManager,
    )
    {
    }

    public function handle(EditWebhookDTO $dto): WebhookDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            /** @var WebhookDomainObject $webhook */
            $webhook = $this->webhookRepository->findFirstWhere([
                'id' => $dto->webhookId,
                'event_id' => $dto->eventId,
            ]);

            if (!$webhook) {
                throw new ResourceNotFoundException(__(
                    key: 'Webhook not found for ID: :webhookId and event ID: :eventId',
                    replace: [
                        'webhookId' => $dto->webhookId,
                        'eventId' => $dto->eventId,
                    ]
                ));
            }

            return $this->webhookRepository->updateFromArray(
                id: $webhook->getId(),
                attributes: [
                    'url' => $dto->url,
                    'event_types' => $dto->eventTypes,
                    'status' => $dto->status->value,
                ]
            );
        });
    }
}
