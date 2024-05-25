<?php

namespace HiEvents\Services\Handlers\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Handlers\Question\DTO\UpsertQuestionDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class CreateQuestionHandler
{
    public function __construct(
        private QuestionRepositoryInterface $questionRepository,
        private DatabaseManager             $databaseManager)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->questionRepository->create([
            QuestionDomainObjectAbstract::TITLE => $createQuestionDTO->title,
            QuestionDomainObjectAbstract::EVENT_ID => $createQuestionDTO->event_id,
            QuestionDomainObjectAbstract::BELONGS_TO => $createQuestionDTO->belongs_to->name,
            QuestionDomainObjectAbstract::TYPE => $createQuestionDTO->type->name,
            QuestionDomainObjectAbstract::REQUIRED => $createQuestionDTO->required,
            QuestionDomainObjectAbstract::OPTIONS => $createQuestionDTO->options,
            QuestionDomainObjectAbstract::IS_HIDDEN => $createQuestionDTO->is_hidden,

        ], $createQuestionDTO->ticket_ids));
    }
}
