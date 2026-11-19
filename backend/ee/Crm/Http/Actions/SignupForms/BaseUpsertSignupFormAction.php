<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\SignupForms;

use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertSignupFormRequest;
use HiEvents\Enterprise\Crm\Resources\SignupFormResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\UpsertSignupFormDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\UpsertSignupFormHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

abstract class BaseUpsertSignupFormAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertSignupFormHandler $handler,
    ) {}

    protected function upsert(UpsertSignupFormRequest $request, int $organizerId, ?int $formId, int $successStatus): JsonResponse
    {
        try {
            $form = $this->handler->handle(new UpsertSignupFormDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                name: $request->validated('name'),
                status: $request->validated('status'),
                title: $request->validated('title'),
                description: $request->validated('description'),
                button_label: $request->validated('button_label'),
                success_message: $request->validated('success_message'),
                asks_for_name: (bool) $request->validated('asks_for_name'),
                privacy_url: $request->validated('privacy_url'),
                tag_ids: array_map('intval', $request->validated('tag_ids')),
                show_on_organizer_page: (bool) $request->validated('show_on_organizer_page'),
                form_id: $formId,
            ));
        } catch (UnknownTagException $exception) {
            throw ValidationException::withMessages(['tag_ids' => $exception->getMessage()]);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(SignupFormResource::class, $form, $successStatus);
    }
}
