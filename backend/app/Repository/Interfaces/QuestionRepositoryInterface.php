<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<QuestionDomainObject>
 */
interface QuestionRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): Collection;

    public function create(array $attributes, array $productIds = []): QuestionDomainObject;

    public function updateQuestion(int $questionId, int $eventId, array $attributes, array $productIds = []): void;

    public function sortQuestions(int $eventId, array $orderedQuestionIds): void;

    /**
     * Bulk-set contact_link_ignored_at on questions, scoped to the given account via events JOIN.
     *
     * @param  int[]  $questionIds
     * @param  ?string  $timestamp  ISO-8601 value to set; null to clear the flag (un-ignore).
     * @return int Number of rows updated.
     */
    public function bulkUpdateContactLinkIgnoredAt(int $accountId, array $questionIds, ?string $timestamp): int;
}
