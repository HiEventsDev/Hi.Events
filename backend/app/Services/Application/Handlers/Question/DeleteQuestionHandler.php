<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class DeleteQuestionHandler
{
    public function __construct(
        private QuestionRepositoryInterface       $questionRepository,
        private QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private DatabaseManager                   $databaseManager,
    )
    {
    }

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
        $existingQuestion = $this->questionRepository->findFirstWhere([
            'id' => $questionId,
            'event_id' => $eventId,
        ]);

        if ($existingQuestion === null) {
            throw new ResourceNotFoundException(__('Question not found'));
        }

        $existingAnswers = $this->questionAnswersRepository->findWhere([
            'question_id' => $questionId,
        ]);

        if ($existingAnswers->isNotEmpty()) {
            throw new CannotDeleteEntityException(
                __('You cannot delete this question as there as answers associated with it. You can hide the question instead.'),
            );
        }

        $this->questionAnswersRepository->deleteWhere([
            'question_id' => $questionId,
        ]);

        $this->questionRepository->deleteWhere([
            'id' => $questionId,
            'event_id' => $eventId,
        ]);
    }
}
