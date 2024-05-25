<?php

namespace HiEvents\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Resources\Question\QuestionResource;

class GetQuestionsAction extends BaseAction
{
    private QuestionRepositoryInterface $questionRepository;

    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $questions = $this->questionRepository
            ->loadRelation(
                new Relationship(TicketDomainObject::class, [
                    new Relationship(TicketPriceDomainObject::class)
                ])
            )
            ->findByEventId($eventId);

        return $this->resourceResponse(QuestionResource::class, $questions);
    }
}
