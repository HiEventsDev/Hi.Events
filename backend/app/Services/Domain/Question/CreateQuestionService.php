<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateQuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly DatabaseManager             $databaseManager,
        private readonly HtmlPurifierService         $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createQuestion(
        QuestionDomainObject $question,
        array                $productIds,
    ): QuestionDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->questionRepository->create([
            QuestionDomainObjectAbstract::TITLE => $question->getTitle(),
            QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
            QuestionDomainObjectAbstract::BELONGS_TO => $question->getBelongsTo(),
            QuestionDomainObjectAbstract::TYPE => $question->getType(),
            QuestionDomainObjectAbstract::REQUIRED => $question->getRequired(),
            QuestionDomainObjectAbstract::OPTIONS => $question->getOptions(),
            QuestionDomainObjectAbstract::IS_HIDDEN => $question->getIsHidden(),
            QuestionDomainObjectAbstract::DESCRIPTION => $this->purifier->purify($question->getDescription()),
        ], $productIds));
    }
}
