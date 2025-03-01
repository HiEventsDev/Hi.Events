<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\Services\Application\Handlers\Question\DTO\EditQuestionAnswerDTO;
use HiEvents\Services\Domain\Question\EditQuestionAnswerService;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use JsonException;

class EditQuestionAnswerHandler
{
    public function __construct(
        private readonly EditQuestionAnswerService $editQuestionAnswerService,
    )
    {
    }

    /**
     * @throws InvalidAnswerException
     * @throws JsonException
     */
    public function handle(EditQuestionAnswerDTO $editQuestionAnswerDTO): void
    {
        $this->editQuestionAnswerService->editQuestionAnswer(
            eventId: $editQuestionAnswerDTO->eventId,
            questionAnswerId: $editQuestionAnswerDTO->questionAnswerId,
            answer: $editQuestionAnswerDTO->answer,
        );
    }
}
