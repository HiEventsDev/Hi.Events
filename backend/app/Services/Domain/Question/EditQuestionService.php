<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class EditQuestionService
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
     * @throws ResourceNotFoundException
     */
    public function editQuestion(
        QuestionDomainObject $question,
        array                $productIds,
    ): QuestionDomainObject
    {
        $existingQuestion = $this->questionRepository->findFirstWhere([
            QuestionDomainObjectAbstract::ID => $question->getId(),
            QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
        ]);

        if ($existingQuestion === null) {
            throw new ResourceNotFoundException(__('Question not found'));
        }

        $this->eventProductValidationService->validateProductIds($productIds, $question->getEventId());

        return $this->databaseManager->transaction(function () use ($question, $productIds) {
            $this->questionRepository->updateQuestion(
                questionId: $question->getId(),
                eventId: $question->getEventId(),
                attributes: [
                    QuestionDomainObjectAbstract::TITLE => $question->getTitle(),
                    QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
                    QuestionDomainObjectAbstract::BELONGS_TO => $question->getBelongsTo(),
                    QuestionDomainObjectAbstract::TYPE => $question->getType(),
                    QuestionDomainObjectAbstract::REQUIRED => $question->getRequired(),
                    QuestionDomainObjectAbstract::OPTIONS => $question->getOptions(),
                    QuestionDomainObjectAbstract::IS_HIDDEN => $question->getIsHidden(),
                    QuestionDomainObjectAbstract::DESCRIPTION => $this->purifier->purify($question->getDescription()),
                ],
                productIds: $productIds
            );

            return $this->questionRepository
                ->loadRelation(ProductDomainObject::class)
                ->findById($question->getId());
        });
    }
}
