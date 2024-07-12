<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\UpsertQuestionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Question\QuestionResource;
use HiEvents\Services\Handlers\Question\CreateQuestionHandler;
use HiEvents\Services\Handlers\Question\DTO\UpsertQuestionDTO;
use Illuminate\Http\JsonResponse;

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
            'description' => $request->input('description'),
        ]));

        return $this->resourceResponse(
            resource: QuestionResource::class,
            data: $question,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
