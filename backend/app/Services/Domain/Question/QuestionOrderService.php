<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Illuminate\Support\Collection;

class QuestionOrderService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
    ) {}

    public function placeFollowUpsAfterParents(int $eventId): void
    {
        $questions = $this->questionRepository
            ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->sortBy([
                fn (QuestionDomainObject $a, QuestionDomainObject $b) => $a->getOrder() <=> $b->getOrder(),
                fn (QuestionDomainObject $a, QuestionDomainObject $b) => $a->getId() <=> $b->getId(),
            ])
            ->values();

        $orderedIds = $this->orderWithFollowUpsAfterParents($questions);
        $currentIds = $questions->map(fn (QuestionDomainObject $question) => $question->getId())->all();
        $isAlreadyNumbered = $questions->every(fn (QuestionDomainObject $question, int $index) => $question->getOrder() === $index + 1);

        if ($orderedIds === $currentIds && $isAlreadyNumbered) {
            return;
        }

        $this->questionRepository->sortQuestions($eventId, $orderedIds);
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @return int[]
     */
    private function orderWithFollowUpsAfterParents(Collection $questions): array
    {
        $questionsById = $questions->keyBy(fn (QuestionDomainObject $question) => $question->getId());
        $followUpsByParentId = [];
        $roots = [];

        foreach ($questions as $question) {
            /** @var QuestionDomainObject|null $parent */
            $parent = $questionsById->get($question->getConditionQuestionId());

            if ($parent !== null && $parent->getBelongsTo() === $question->getBelongsTo()) {
                $followUpsByParentId[$parent->getId()][] = $question;
            } else {
                $roots[] = $question;
            }
        }

        $ordered = [];
        $visit = function (QuestionDomainObject $question) use (&$visit, &$ordered, $followUpsByParentId): void {
            if (isset($ordered[$question->getId()])) {
                return;
            }

            $ordered[$question->getId()] = true;

            foreach ($followUpsByParentId[$question->getId()] ?? [] as $followUp) {
                $visit($followUp);
            }
        };

        foreach ([...$roots, ...$questions->all()] as $question) {
            $visit($question);
        }

        return array_keys($ordered);
    }
}
