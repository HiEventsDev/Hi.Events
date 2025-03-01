<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EditQuestionAnswerService
{
    public function __construct(
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
        private readonly LoggerInterface                   $logger,
    )
    {
    }

    /**
     * @throws InvalidAnswerException
     * @throws \JsonException
     */
    public function editQuestionAnswer(int $eventId, int $questionAnswerId, null|string|array $answer): void
    {
        $questionAnswer = $this->questionAnswerRepository
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, name: 'order'))
            ->loadRelation(new Relationship(domainObject: QuestionDomainObject::class, name: 'question'))
            ->findById($questionAnswerId);

        /** @var QuestionDomainObject $question */
        $question = $questionAnswer->getQuestion();
        /** @var OrderDomainObject $order */
        $order = $questionAnswer->getOrder();

        if ($order->getEventId() !== $eventId) {
            $this->logger->error('Question answer does not belong to the event', [
                'event_id' => $eventId,
                'question_answer_id' => $questionAnswerId,
            ]);

            throw new ResourceNotFoundException('Question answer does not belong to the event');
        }

        if (!$question->isAnswerValid($answer)) {
            $this->logger->error('Invalid answer', [
                'question_id' => $question->getId(),
                'answer' => $answer,
            ]);

            throw new InvalidAnswerException('Please provide a valid answer');
        }

        $this->questionAnswerRepository->updateWhere(
            attributes: [
                'answer' => json_encode($answer, JSON_THROW_ON_ERROR),
            ],
            where: [
                'id' => $questionAnswerId,
                'order_id' => $order->getId(),
            ],
        );
    }
}
