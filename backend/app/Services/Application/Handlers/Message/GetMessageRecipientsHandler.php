<?php

namespace HiEvents\Services\Application\Handlers\Message;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetMessageRecipientsHandler
{
    public function __construct(
        private readonly OutgoingMessageRepositoryInterface $outgoingMessageRepository,
        private readonly MessageRepositoryInterface         $messageRepository,
    )
    {
    }

    public function handle(int $eventId, int $messageId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $message = $this->messageRepository->findFirstWhere([
            'id' => $messageId,
            'event_id' => $eventId,
        ]);

        if ($message === null) {
            throw new ResourceNotFoundException(__('Message not found'));
        }

        return $this->outgoingMessageRepository->paginateWhere(
            where: [
                'event_id' => $eventId,
                'message_id' => $messageId,
            ],
            limit: $params->per_page,
        );
    }
}
