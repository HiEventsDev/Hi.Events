<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\EditQuestionAnswerRequest;
use HiEvents\Services\Application\Handlers\Question\DTO\EditQuestionAnswerDTO;
use HiEvents\Services\Application\Handlers\Question\EditQuestionAnswerHandler;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class EditQuestionAnswerAction extends BaseAction
{
    public function __construct(
        private readonly EditQuestionAnswerHandler $editQuestionAnswerHandler,
    )
    {
    }

    public function __invoke(
        int                       $eventId,
        int                       $questionId,
        int                       $questionAnswerId,
        EditQuestionAnswerRequest $request,
    ): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->editQuestionAnswerHandler->handle(new EditQuestionAnswerDTO(
                questionAnswerId: $questionAnswerId,
                eventId: $eventId,
                answer: $request->validated('answer'),
            ));
        } catch (InvalidAnswerException $e) {
            throw ValidationException::withMessages(['answer.answer' => $e->getMessage()]);
        }

        return $this->noContentResponse();
    }
}
