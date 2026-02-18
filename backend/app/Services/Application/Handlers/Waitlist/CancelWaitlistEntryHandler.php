<?php

namespace HiEvents\Services\Application\Handlers\Waitlist;

use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Waitlist\CancelWaitlistEntryService;

class CancelWaitlistEntryHandler
{
    public function __construct(
        private readonly CancelWaitlistEntryService $cancelWaitlistEntryService,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handleCancelByToken(string $cancelToken): WaitlistEntryDomainObject
    {
        return $this->cancelWaitlistEntryService->cancelByToken($cancelToken);
    }

    /**
     * @throws ResourceConflictException
     */
    public function handleCancelById(int $entryId, int $eventId): WaitlistEntryDomainObject
    {
        return $this->cancelWaitlistEntryService->cancelById($entryId, $eventId);
    }
}
