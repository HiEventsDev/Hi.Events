<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\UpsertQuestionRequest;
use HiEvents\Resources\Question\QuestionResource;
use HiEvents\Services\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Handlers\Question\EditQuestionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class EditQuestionAction extends BaseAction
{
    private EditQuestionHandler $editQuestionHandler;

    public function __construct(EditQuestionHandler $editQuestionHandler)
    {
        $this->editQuestionHandler = $editQuestionHandler;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(UpsertQuestionRequest $request, int $eventId, int $questionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $question = $this->editQuestionHandler->handle(
            questionId: $questionId,
            createQuestionDTO: UpsertQuestionDTO::fromArray([
                'title' => $request->input('title'),
                'type' => QuestionTypeEnum::fromName($request->input('type')),
                'required' => $request->boolean('required'),
                'options' => $request->input('options'),
                'event_id' => $eventId,
                'ticket_ids' => $request->input('ticket_ids'),
                'is_hidden' => $request->boolean('is_hidden'),
                'belongs_to' => QuestionBelongsTo::fromName($request->input('belongs_to')),
                'description' => $request->input('description'),
            ]));

        return $this->resourceResponse(QuestionResource::class, $question);
    }
}
