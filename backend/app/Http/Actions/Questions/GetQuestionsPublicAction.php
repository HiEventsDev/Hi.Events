<?php

namespace HiEvents\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Resources\Question\QuestionResourcePublic;

class GetQuestionsPublicAction extends BaseAction
{
    private QuestionRepositoryInterface $questionRepository;

    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $questions = $this->questionRepository
            ->loadRelation(TicketDomainObject::class)
            ->findWhere([
                QuestionDomainObjectAbstract::EVENT_ID => $eventId,
                QuestionDomainObjectAbstract::IS_HIDDEN => false,
            ])
            ->sortBy('id');

        return $this->resourceResponse(QuestionResourcePublic::class, $questions);
    }
}
