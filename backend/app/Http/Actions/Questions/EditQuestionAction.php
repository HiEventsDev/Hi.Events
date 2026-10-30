<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidQuestionLogicException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\UpsertQuestionRequest;
use HiEvents\Resources\Question\QuestionResource;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Application\Handlers\Question\EditQuestionHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
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

        try {
            $question = $this->editQuestionHandler->handle(
                questionId: $questionId,
                createQuestionDTO: UpsertQuestionDTO::fromArray([
                    'title' => $request->input('title'),
                    'type' => QuestionTypeEnum::fromName($request->input('type')),
                    'required' => $request->boolean('required'),
                    'options' => $request->input('options'),
                    'event_id' => $eventId,
                    'product_ids' => $request->input('product_ids', []),
                    'is_hidden' => $request->boolean('is_hidden'),
                    'belongs_to' => QuestionBelongsTo::fromName($request->input('belongs_to')),
                    'description' => $request->input('description'),
                    'applies_to_all_tickets' => $request->boolean('applies_to_all_tickets'),
                    'condition_question_id' => $request->filled('condition_question_id') ? $request->integer('condition_question_id') : null,
                    'condition_values' => $request->input('condition_values'),
                    'condition_provided' => $request->exists('condition_question_id'),
                    'disqualifying_options' => $request->input('disqualifying_options'),
                    'disqualification_message' => $request->input('disqualification_message'),
                    'disqualification_provided' => $request->exists('disqualifying_options'),
                    'option_renames' => $request->input('option_renames', []),
                ]));
        } catch (UnrecognizedProductIdException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (InvalidQuestionLogicException $exception) {
            throw ValidationException::withMessages([$exception->getField() => $exception->getMessage()]);
        }

        return $this->resourceResponse(QuestionResource::class, $question);
    }
}
