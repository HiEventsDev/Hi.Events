<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Resources\Question\QuestionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetQuestionAction extends BaseAction
{
    private QuestionRepositoryInterface $questionRepository;

    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, int $eventId, int $questionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $question = $this->questionRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([
                QuestionDomainObjectAbstract::ID => $questionId,
                QuestionDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

        if ($question === null) {
            throw new ResourceNotFoundException(__('Question not found'));
        }

        return $this->resourceResponse(QuestionResource::class, $question);
    }
}
