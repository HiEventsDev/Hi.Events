<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

class EditQuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly DatabaseManager             $databaseManager,
        private readonly HTMLPurifier                $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function editQuestion(
        QuestionDomainObject $question,
        array                $ticketIds,
    ): QuestionDomainObject
    {
        return $this->databaseManager->transaction(function () use ($question, $ticketIds) {
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
                ticketIds: $ticketIds
            );

            return $this->questionRepository
                ->loadRelation(TicketDomainObject::class)
                ->findById($question->getId());
        });
    }
}
