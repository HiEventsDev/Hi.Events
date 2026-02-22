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
}
