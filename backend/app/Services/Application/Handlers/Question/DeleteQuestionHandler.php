<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

readonly class DeleteQuestionHandler
{
    public function __construct(
        private QuestionRepositoryInterface $questionRepository,
        private QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $questionId, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($questionId, $eventId) {
            $this->deleteQuestion($questionId, $eventId);
        });
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws ResourceNotFoundException
     */
    private function deleteQuestion(int $questionId, int $eventId): void
    {
        $eventQuestions = $this->questionRepository->findWhere([
            QuestionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (! $eventQuestions->contains(fn (QuestionDomainObject $question) => $question->getId() === $questionId)) {
            throw new ResourceNotFoundException(__('Question not found'));
        }

        $followUpIds = $this->followUpIds($questionId, $eventQuestions);
        $questionIds = [$questionId, ...$followUpIds];

        if ($this->hasAnswers([$questionId])) {
            throw new CannotDeleteEntityException(
                __('You cannot delete this question as there as answers associated with it. You can hide the question instead.'),
            );
        }

        if ($followUpIds !== [] && $this->hasAnswers($followUpIds)) {
            throw new CannotDeleteEntityException(
                __('You cannot delete this question because its follow-up questions have answers. You can hide the question instead.'),
            );
        }

        foreach (array_reverse($questionIds) as $id) {
            $this->questionRepository->deleteWhere([
                QuestionDomainObjectAbstract::ID => $id,
                QuestionDomainObjectAbstract::EVENT_ID => $eventId,
            ]);
        }
    }

    /**
     * @param  int[]  $questionIds
     */
    private function hasAnswers(array $questionIds): bool
    {
        return $this->questionAnswersRepository->countWhere([
            [QuestionAnswerDomainObjectAbstract::QUESTION_ID, 'in', $questionIds],
        ]) > 0;
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $eventQuestions
     * @return int[]
     */
    private function followUpIds(int $questionId, Collection $eventQuestions): array
    {
        $followUpIds = [];
        $parentIds = [$questionId];

        while ($parentIds !== []) {
            $children = $eventQuestions
                ->filter(fn (QuestionDomainObject $question) => in_array($question->getConditionQuestionId(), $parentIds, true))
                ->map(fn (QuestionDomainObject $question) => $question->getId())
                ->reject(fn (int $id) => $id === $questionId || in_array($id, $followUpIds, true))
                ->values()
                ->all();

            $followUpIds = [...$followUpIds, ...$children];
            $parentIds = $children;
        }

        return $followUpIds;
    }
}
