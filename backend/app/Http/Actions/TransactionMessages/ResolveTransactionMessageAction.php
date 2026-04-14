<?php

namespace HiEvents\Http\Actions\TransactionMessages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OutgoingTransactionMessageDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Resources\TransactionMessage\OutgoingTransactionMessageResource;
use Illuminate\Http\JsonResponse;

class ResolveTransactionMessageAction extends BaseAction
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $message = $this->repository->findFirstWhere([
            OutgoingTransactionMessageDomainObjectAbstract::ID => $messageId,
            OutgoingTransactionMessageDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$message) {
            return $this->notFoundResponse();
        }

        $resolvedAt = $message->getResolvedAt() ? null : now()->toDateTimeString();

        $this->repository->updateWhere(
            attributes: [OutgoingTransactionMessageDomainObjectAbstract::RESOLVED_AT => $resolvedAt],
            where: [OutgoingTransactionMessageDomainObjectAbstract::ID => $messageId],
        );

        $updated = $this->repository->findById($messageId);

        return $this->resourceResponse(OutgoingTransactionMessageResource::class, $updated);
    }
}
