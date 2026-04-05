<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Pos;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PosSessionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPosSessionsAction extends BaseAction
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $posSessionRepository,
    ) {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $sessions = $this->posSessionRepository->findByEventId($eventId);

        return $this->jsonResponse($sessions->map(fn($s) => $s->toArray())->toArray());
    }
}
