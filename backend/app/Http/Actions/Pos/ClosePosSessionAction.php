<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Pos;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PosSessionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClosePosSessionAction extends BaseAction
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $posSessionRepository,
    ) {
    }

    public function __invoke(int $eventId, int $sessionId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $session = $this->posSessionRepository->updateWhere(
            attributes: [
                'status' => 'closed',
                'closed_at' => now(),
            ],
            where: [
                'id' => $sessionId,
                'event_id' => $eventId,
            ],
        );

        return $this->jsonResponse($session->toArray());
    }
}
