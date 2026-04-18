<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\Question\EditQuestionService;
use HiEvents\Services\Domain\Question\QuestionContactAttributeLinker;
use Throwable;

class EditQuestionHandler
{
    public function __construct(
        private readonly EditQuestionService            $editQuestionService,
        private readonly QuestionContactAttributeLinker $contactAttributeLinker,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $questionId, UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $this->contactAttributeLinker->validate(
            eventId: $createQuestionDTO->event_id,
            definitionId: $createQuestionDTO->contact_attribute_definition_id,
            questionType: $createQuestionDTO->type,
        );

        $question = (new QuestionDomainObject())
            ->setId($questionId)
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setDescription($createQuestionDTO->description)
            ->setContactAttributeDefinitionId($createQuestionDTO->contact_attribute_definition_id);

        return $this->editQuestionService->editQuestion(
            question: $question,
            productIds: $createQuestionDTO->product_ids,
        );
    }
}
