<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\SortQuestionsRequest;
use HiEvents\Services\Handlers\Question\SortQuestionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class SortQuestionsAction extends BaseAction
{
    public function __construct(
        private readonly SortQuestionsHandler $sortQuestionsHandler
    )
    {
    }

    public function __invoke(SortQuestionsRequest $request, int $eventId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->sortQuestionsHandler->handle(
                $eventId,
                $request->validated(),
            );
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->noContentResponse();
    }
}
