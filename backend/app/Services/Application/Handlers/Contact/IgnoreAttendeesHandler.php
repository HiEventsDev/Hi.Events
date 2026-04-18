<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;

readonly class IgnoreAttendeesHandler
{
    public function __construct(
        private AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    /**
     * @param  int[]  $attendeeIds
     */
    public function handle(int $accountId, array $attendeeIds): int
    {
        return $this->attendeeRepository->bulkUpdateContactLinkIgnoredAt(
            accountId: $accountId,
            attendeeIds: $attendeeIds,
            timestamp: now()->toDateTimeString(),
        );
    }
}
