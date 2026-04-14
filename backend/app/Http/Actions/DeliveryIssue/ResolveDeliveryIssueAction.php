<?php

namespace HiEvents\Http\Actions\DeliveryIssue;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OutgoingMessageDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OutgoingTransactionMessageDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResolveDeliveryIssueAction extends BaseAction
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $transactionMessageRepository,
        private readonly OutgoingMessageRepositoryInterface $outgoingMessageRepository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->validate($request, [
            'source_type' => 'required|in:transaction,announcement',
        ]);

        $sourceType = $request->input('source_type');

        if ($sourceType === 'transaction') {
            return $this->resolveTransactionMessage($eventId, $messageId);
        }

        return $this->resolveOutgoingMessage($eventId, $messageId);
    }

    private function resolveTransactionMessage(int $eventId, int $messageId): JsonResponse
    {
        $message = $this->transactionMessageRepository->findFirstWhere([
            OutgoingTransactionMessageDomainObjectAbstract::ID => $messageId,
            OutgoingTransactionMessageDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$message) {
            return $this->notFoundResponse();
        }

        $isResolving = !$message->getResolvedAt();
        $resolvedAt = $isResolving ? now()->toDateTimeString() : null;
        $resolutionType = $isResolving ? 'manual' : null;

        $this->transactionMessageRepository->updateWhere(
            attributes: [
                OutgoingTransactionMessageDomainObjectAbstract::RESOLVED_AT => $resolvedAt,
                OutgoingTransactionMessageDomainObjectAbstract::RESOLUTION_TYPE => $resolutionType,
            ],
            where: [OutgoingTransactionMessageDomainObjectAbstract::ID => $messageId],
        );

        $updated = $this->transactionMessageRepository->findById($messageId);

        return $this->jsonResponse([
            'id' => $updated->getId(),
            'source_type' => 'transaction',
            'email_type' => $updated->getEmailType(),
            'status' => $updated->getStatus(),
            'subject' => $updated->getSubject(),
            'recipient' => $updated->getRecipient(),
            'updated_at' => $updated->getUpdatedAt(),
            'resolved_at' => $updated->getResolvedAt(),
            'resolution_type' => $updated->getResolutionType(),
        ]);
    }

    private function resolveOutgoingMessage(int $eventId, int $messageId): JsonResponse
    {
        $message = $this->outgoingMessageRepository->findFirstWhere([
            OutgoingMessageDomainObjectAbstract::ID => $messageId,
            OutgoingMessageDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$message) {
            return $this->notFoundResponse();
        }

        $isResolving = !$message->getResolvedAt();
        $resolvedAt = $isResolving ? now()->toDateTimeString() : null;
        $resolutionType = $isResolving ? 'manual' : null;

        $this->outgoingMessageRepository->updateWhere(
            attributes: [
                OutgoingMessageDomainObjectAbstract::RESOLVED_AT => $resolvedAt,
                OutgoingMessageDomainObjectAbstract::RESOLUTION_TYPE => $resolutionType,
            ],
            where: [OutgoingMessageDomainObjectAbstract::ID => $messageId],
        );

        $updated = $this->outgoingMessageRepository->findById($messageId);

        return $this->jsonResponse([
            'id' => $updated->getId(),
            'source_type' => 'announcement',
            'email_type' => 'announcement',
            'status' => $updated->getStatus(),
            'subject' => $updated->getSubject(),
            'recipient' => $updated->getRecipient(),
            'updated_at' => $updated->getUpdatedAt(),
            'resolved_at' => $updated->getResolvedAt(),
        ]);
    }
}
