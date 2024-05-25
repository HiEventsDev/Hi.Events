<?php

namespace HiEvents\Repository\Interfaces;

use Illuminate\Support\Collection;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<QuestionDomainObject>
 */
interface QuestionRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): Collection;

    public function create(array $attributes, array $ticketIds = []): QuestionDomainObject;

    public function updateQuestion(int $questionId, int $eventId, array $attributes, array $ticketIds = []): void;

    public function sortQuestions(int $eventId, array $orderedQuestionIds): void;
}
