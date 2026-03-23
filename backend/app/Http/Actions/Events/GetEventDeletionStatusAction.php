<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Illuminate\Http\JsonResponse;

class GetEventDeletionStatusAction extends BaseAction
{
    public function __construct(
        private readonly EventDeletionService $eventDeletionService,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class, Role::READONLY);

        $canDelete = $this->eventDeletionService->canDeleteEvent($eventId);

        return $this->jsonResponse([
            'data' => [
                'can_delete' => $canDelete,
                'reason' => $canDelete ? null : __('This event has completed orders. Please cancel or refund all orders before deleting.'),
            ],
        ]);
    }
}
