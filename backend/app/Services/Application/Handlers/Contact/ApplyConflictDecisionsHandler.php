<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\Services\Domain\Contact\ContactBackfillService;

readonly class ApplyConflictDecisionsHandler
{
    public function __construct(
        private ContactBackfillService $service,
    ) {}

    /**
     * @param  array<array{question_answer_id:int,decision:string}>  $decisions
     */
    public function handle(int $accountId, array $decisions, int $userId): int
    {
        return $this->service->applyConflictDecisions($accountId, $decisions, $userId);
    }
}
