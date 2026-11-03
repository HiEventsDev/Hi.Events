<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidQuestionLogicException;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateQuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly HtmlPurifierService $purifier,
        private readonly EventProductValidationService $eventProductValidationService,
        private readonly QuestionTicketScopeService $questionTicketScopeService,
        private readonly QuestionLogicService $questionLogicService,
        private readonly QuestionOrderService $questionOrderService,
    ) {}

    /**
     * @throws Throwable
     * @throws UnrecognizedProductIdException
     * @throws InvalidQuestionLogicException
     */
    public function createQuestion(
        QuestionDomainObject $question,
        array $productIds,
    ): QuestionDomainObject {
        $this->eventProductValidationService->validateProductIds($productIds, $question->getEventId());

        $eventQuestions = $this->questionRepository->findWhere([
            QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
        ]);

        $this->questionLogicService->normalize($question);
        $this->questionLogicService->validate($question, $eventQuestions, collect());

        return $this->databaseManager->transaction(function () use ($question, $productIds, $eventQuestions) {
            $createdQuestion = $this->questionRepository->create([
                QuestionDomainObjectAbstract::TITLE => $question->getTitle(),
                QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
                QuestionDomainObjectAbstract::BELONGS_TO => $question->getBelongsTo(),
                QuestionDomainObjectAbstract::TYPE => $question->getType(),
                QuestionDomainObjectAbstract::REQUIRED => $question->getRequired(),
                QuestionDomainObjectAbstract::OPTIONS => $question->getOptions(),
                QuestionDomainObjectAbstract::IS_HIDDEN => $question->getIsHidden(),
                QuestionDomainObjectAbstract::DESCRIPTION => $this->purifier->purify($question->getDescription()),
                QuestionDomainObjectAbstract::APPLIES_TO_ALL_TICKETS => $this->questionTicketScopeService->appliesToAllTickets($question),
                QuestionDomainObjectAbstract::CONDITION_QUESTION_ID => $question->getConditionQuestionId(),
                QuestionDomainObjectAbstract::CONDITION_VALUES => $question->getConditionValues(),
                QuestionDomainObjectAbstract::DISQUALIFYING_OPTIONS => $question->getDisqualifyingOptions(),
                QuestionDomainObjectAbstract::DISQUALIFICATION_MESSAGE => $question->getDisqualificationMessage(),
                QuestionDomainObjectAbstract::ORDER => $eventQuestions->max(fn (QuestionDomainObject $eventQuestion) => $eventQuestion->getOrder()) + 1,
            ], $this->questionTicketScopeService->resolveProductIds($question, $productIds));

            if ($createdQuestion->getConditionQuestionId() !== null) {
                $this->questionOrderService->placeFollowUpsAfterParents($question->getEventId());
            }

            return $this->questionRepository
                ->loadRelation(ProductDomainObject::class)
                ->findById($createdQuestion->getId());
        });
    }
}
