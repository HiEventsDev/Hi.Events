<?php

namespace HiEvents\Services\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Domain\Question\CreateQuestionService;
use HiEvents\Services\Handlers\Question\DTO\UpsertQuestionDTO;
use HTMLPurifier;
use Throwable;

class CreateQuestionHandler
{
    public function __construct(
        private readonly CreateQuestionService $createQuestionService,
        private readonly HTMLPurifier          $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $question = (new QuestionDomainObject())
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setDescription($this->purifier->purify($createQuestionDTO->description));

        return $this->createQuestionService->createQuestion(
            $question,
            $createQuestionDTO->ticket_ids,
        );
    }
}
