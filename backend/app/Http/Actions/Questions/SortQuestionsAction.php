<?php

namespace TicketKitten\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\Request\Questions\SortQuestionsRequest;
use TicketKitten\Service\Handler\Question\SortQuestionsHandler;

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
