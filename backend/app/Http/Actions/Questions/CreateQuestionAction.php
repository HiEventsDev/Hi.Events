<?php

namespace TicketKitten\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\QuestionBelongsTo;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpsertQuestionDTO;
use TicketKitten\Http\Request\Questions\UpsertQuestionRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Question\QuestionResource;
use TicketKitten\Service\Handler\Question\CreateQuestionHandler;

class CreateQuestionAction extends BaseAction
{
    private CreateQuestionHandler $createQuestionHandler;

    public function __construct(CreateQuestionHandler $createQuestionHandler)
    {
        $this->createQuestionHandler = $createQuestionHandler;
    }

    public function __invoke(UpsertQuestionRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $question = $this->createQuestionHandler->handle(UpsertQuestionDTO::fromArray([
            'title' => $request->input('title'),
            'type' => $request->input('type'),
            'required' => $request->boolean('required'),
            'options' => $request->input('options'),
            'event_id' => $eventId,
            'ticket_ids' => $request->input('ticket_ids'),
            'belongs_to' => $request->input('belongs_to'),
            'is_hidden' => $request->boolean('is_hidden'),
        ]));

        return $this->resourceResponse(QuestionResource::class, $question, ResponseCodes::HTTP_CREATED);
    }
}
