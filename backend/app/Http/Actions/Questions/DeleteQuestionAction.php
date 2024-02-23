<?php

namespace TicketKitten\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\CannotDeleteEntityException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Service\Handler\Question\DeleteQuestionHandler;

class DeleteQuestionAction extends BaseAction
{
    private DeleteQuestionHandler $deleteQuestionHandler;

    public function __construct(DeleteQuestionHandler $deleteQuestionHandler)
    {
        $this->deleteQuestionHandler = $deleteQuestionHandler;
    }

    /**
     * @throws Throwable
     * @throws CannotDeleteEntityException
     */
    public function __invoke(int $eventId, int $questionId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->deleteQuestionHandler->handle($questionId, $eventId);
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->deletedResponse();
    }
}
