<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\Question\EditQuestionService;
use Throwable;

class EditQuestionHandler
{
    public function __construct(
        private readonly EditQuestionService $editQuestionService,

    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $questionId, UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $question = (new QuestionDomainObject())
            ->setId($questionId)
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setDescription($createQuestionDTO->description);

        return $this->editQuestionService->editQuestion(
            question: $question,
            productIds: $createQuestionDTO->product_ids,
        );
    }
}
