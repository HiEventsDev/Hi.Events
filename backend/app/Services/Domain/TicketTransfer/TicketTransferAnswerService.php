<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Question\QuestionVisibilityService;

class TicketTransferAnswerService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
        private readonly QuestionVisibilityService $questionVisibilityService,
    ) {}

    /**
     * @param  array<int, array{question_id: int|string, response: mixed}>  $questions
     */
    public function replaceAnswers(AttendeeDomainObject $attendee, array $questions): void
    {
        $this->questionAnswerRepository->forceDeleteForAttendee($attendee->getId());

        $questionsById = $this->questionVisibilityService->keyById(
            $this->questionRepository
                ->loadRelation(new Relationship(ProductDomainObject::class))
                ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $attendee->getEventId()])
        );
        $attendeeAnswers = $this->questionVisibilityService->answersByQuestionId($questions);

        foreach ($questions as $question) {
            $answer = $this->questionVisibilityService->extractAnswer($question['response'] ?? null);

            if ($answer === null || $answer === '' || $answer === []) {
                continue;
            }

            if (! $this->questionVisibilityService->shouldKeepAnswer(
                (int) $question['question_id'],
                $questionsById,
                [],
                $attendeeAnswers,
                $attendee->getProductId(),
            )) {
                continue;
            }

            $this->questionAnswerRepository->create([
                'question_id' => (int) $question['question_id'],
                'answer' => $answer,
                'order_id' => $attendee->getOrderId(),
                'product_id' => $attendee->getProductId(),
                'attendee_id' => $attendee->getId(),
            ]);
        }
    }
}
