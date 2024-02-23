<?php

namespace TicketKitten\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\QuestionBelongsTo;
use TicketKitten\DomainObjects\Enums\QuestionTypeEnum;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpsertQuestionDTO;
use TicketKitten\Http\Request\Questions\UpsertQuestionRequest;
use TicketKitten\Resources\Question\QuestionResource;
use TicketKitten\Service\Handler\Question\EditQuestionHandler;

class EditQuestionAction extends BaseAction
{
    private EditQuestionHandler $editQuestionHandler;

    public function __construct(EditQuestionHandler $editQuestionHandler)
    {
        $this->editQuestionHandler = $editQuestionHandler;
    }

    public function __invoke(UpsertQuestionRequest $request, int $eventId, int $questionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $question = $this->editQuestionHandler->handle($questionId, UpsertQuestionDTO::fromArray([
            'title' => $request->input('title'),
            'type' => QuestionTypeEnum::fromName($request->input('type')),
            'required' => $request->boolean('required'),
            'options' => $request->input('options'),
            'event_id' => $eventId,
            'ticket_ids' => $request->input('ticket_ids'),
            'is_hidden' => $request->boolean('is_hidden'),
            'belongs_to' => QuestionBelongsTo::fromName($request->input('belongs_to')),
        ]));

        return $this->resourceResponse(QuestionResource::class, $question);
    }
}
