<?php

namespace HiEvents\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Resources\Question\QuestionResource;

class GetQuestionAction extends BaseAction
{
    private QuestionRepositoryInterface $questionRepository;

    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    public function __invoke(Request $request, int $eventId, int $questionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $questions = $this->questionRepository
            ->loadRelation(TicketDomainObject::class)
            ->findById($questionId);

        return $this->resourceResponse(QuestionResource::class, $questions);
    }
}
