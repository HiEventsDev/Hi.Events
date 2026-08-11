<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateQuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface   $questionRepository,
        private readonly DatabaseManager               $databaseManager,
        private readonly HtmlPurifierService           $purifier,
        private readonly EventProductValidationService $eventProductValidationService,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws UnrecognizedProductIdException
     */
    public function createQuestion(
        QuestionDomainObject $question,
        array                $productIds,
    ): QuestionDomainObject
    {
        $this->eventProductValidationService->validateProductIds($productIds, $question->getEventId());

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
