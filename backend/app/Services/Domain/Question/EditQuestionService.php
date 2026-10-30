<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidQuestionLogicException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class EditQuestionService
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
     * @param  array<int, array{from: string, to: string}>  $optionRenames
     *
     * @throws Throwable
     * @throws UnrecognizedProductIdException
     * @throws ResourceNotFoundException
     * @throws InvalidQuestionLogicException
     */
    public function editQuestion(
        QuestionDomainObject $question,
        array $productIds,
        array $optionRenames = [],
        bool $keepExistingCondition = false,
        bool $keepExistingDisqualification = false,
    ): QuestionDomainObject {
        $eventQuestions = $this->questionRepository->findWhere([
            QuestionDomainObjectAbstract::EVENT_ID => $question->getEventId(),
        ]);

        /** @var QuestionDomainObject|null $existingQuestion */
        $existingQuestion = $eventQuestions->first(
            fn (QuestionDomainObject $eventQuestion) => $eventQuestion->getId() === $question->getId()
        );

        if ($existingQuestion === null) {
            throw new ResourceNotFoundException(__('Question not found'));
        }

        $this->eventProductValidationService->validateProductIds($productIds, $question->getEventId());

        if ($keepExistingCondition) {
            $question
                ->setConditionQuestionId($existingQuestion->getConditionQuestionId())
                ->setConditionValues($existingQuestion->getConditionValues());
        }

        if ($keepExistingDisqualification) {
            $question
                ->setDisqualifyingOptions($this->questionLogicService->renameValues(
                    $existingQuestion->getDisqualifyingOptionsList(),
                    $optionRenames,
                ))
                ->setDisqualificationMessage($existingQuestion->getDisqualificationMessage());
        }

        $this->questionLogicService->normalize($question);

        $dependents = $this->renamedDependents($question, $eventQuestions, $optionRenames);

        $this->questionLogicService->validate($question, $eventQuestions, $dependents);

        return $this->databaseManager->transaction(function () use ($question, $productIds, $dependents, $existingQuestion, $optionRenames) {
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
                    QuestionDomainObjectAbstract::APPLIES_TO_ALL_TICKETS => $this->questionTicketScopeService->appliesToAllTickets($question),
                    QuestionDomainObjectAbstract::CONDITION_QUESTION_ID => $question->getConditionQuestionId(),
                    QuestionDomainObjectAbstract::CONDITION_VALUES => $question->getConditionValues(),
                    QuestionDomainObjectAbstract::DISQUALIFYING_OPTIONS => $question->getDisqualifyingOptions(),
                    QuestionDomainObjectAbstract::DISQUALIFICATION_MESSAGE => $question->getDisqualificationMessage(),
                ],
                productIds: $this->questionTicketScopeService->resolveProductIds($question, $productIds),
            );

            if ($optionRenames !== []) {
                foreach ($dependents as $dependent) {
                    $this->questionRepository->updateFromArray($dependent->getId(), [
                        QuestionDomainObjectAbstract::CONDITION_VALUES => $dependent->getConditionValues(),
                    ]);
                }
            }

            if ($question->getConditionQuestionId() !== $existingQuestion->getConditionQuestionId()) {
                $this->questionOrderService->placeFollowUpsAfterParents($question->getEventId());
            }

            return $this->questionRepository
                ->loadRelation(ProductDomainObject::class)
                ->findById($question->getId());
        });
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $eventQuestions
     * @return Collection<int, QuestionDomainObject>
     */
    private function renamedDependents(QuestionDomainObject $question, Collection $eventQuestions, array $optionRenames): Collection
    {
        return $eventQuestions
            ->filter(fn (QuestionDomainObject $eventQuestion) => $eventQuestion->getConditionQuestionId() === $question->getId())
            ->map(fn (QuestionDomainObject $dependent) => $this->questionLogicService->renameConditionValues($dependent, $optionRenames))
            ->values();
    }
}
