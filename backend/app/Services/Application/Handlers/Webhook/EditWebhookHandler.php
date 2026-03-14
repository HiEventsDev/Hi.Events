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
            $where = ['id' => $dto->webhookId, 'account_id' => $dto->accountId];
            if ($dto->eventId !== null) {
                $where['event_id'] = $dto->eventId;
            }
            if ($dto->organizerId !== null) {
                $where['organizer_id'] = $dto->organizerId;
            }

            /** @var WebhookDomainObject $webhook */
            $webhook = $this->webhookRepository->findFirstWhere($where);

            if (!$webhook) {
                throw new ResourceNotFoundException(__(
                    key: 'Webhook not found for ID: :webhookId',
                    replace: [
                        'webhookId' => $dto->webhookId,
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
