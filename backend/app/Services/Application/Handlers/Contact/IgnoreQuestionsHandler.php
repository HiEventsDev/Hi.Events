<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;

readonly class IgnoreQuestionsHandler
{
    public function __construct(
        private QuestionRepositoryInterface $questionRepository,
    ) {}

    /**
     * @param  int[]  $questionIds
     */
    public function handle(int $accountId, array $questionIds): int
    {
        return $this->questionRepository->bulkUpdateContactLinkIgnoredAt(
            accountId: $accountId,
            questionIds: $questionIds,
            timestamp: now()->toDateTimeString(),
        );
    }
}
