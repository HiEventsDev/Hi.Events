<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\Question\CreateQuestionService;
use HiEvents\Services\Domain\Question\QuestionContactAttributeLinker;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Throwable;

class CreateQuestionHandler
{
    public function __construct(
        private readonly CreateQuestionService          $createQuestionService,
        private readonly HtmlPurifierService            $purifier,
        private readonly QuestionContactAttributeLinker $contactAttributeLinker,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $this->contactAttributeLinker->validate(
            eventId: $createQuestionDTO->event_id,
            definitionId: $createQuestionDTO->contact_attribute_definition_id,
            questionType: $createQuestionDTO->type,
        );

        $question = (new QuestionDomainObject())
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setDescription($this->purifier->purify($createQuestionDTO->description))
            ->setContactAttributeDefinitionId($createQuestionDTO->contact_attribute_definition_id);

        return $this->createQuestionService->createQuestion(
            $question,
            $createQuestionDTO->product_ids,
        );
    }
}
