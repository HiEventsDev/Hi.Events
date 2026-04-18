<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\Services\Domain\Contact\ContactBackfillService;

readonly class AddContactsFromAttendeesHandler
{
    public function __construct(
        private ContactBackfillService $service,
    ) {}

    /**
     * @param  int[]  $attendeeIds
     */
    public function handle(int $accountId, array $attendeeIds): int
    {
        return $this->service->linkAttendeesById($accountId, $attendeeIds);
    }
}
