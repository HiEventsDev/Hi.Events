<?php

namespace HiEvents\Http\Actions\Questions;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\UpsertQuestionDTO;
use HiEvents\Http\Request\Questions\UpsertQuestionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Question\QuestionResource;
use HiEvents\Service\Handler\Question\CreateQuestionHandler;

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
