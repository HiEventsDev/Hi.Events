<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Contact\ApplyConflictDecisionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplyConflictDecisionsAction extends BaseAction
{
    public function __construct(
        private readonly ApplyConflictDecisionsHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.question_answer_id' => ['required', 'integer'],
            'decisions.*.decision' => ['required', Rule::in(['update', 'leave_alone'])],
        ]);

        $count = $this->handler->handle(
            accountId: $this->getAuthenticatedAccountId(),
            decisions: $validated['decisions'],
            userId: $this->getAuthenticatedUser()->getId(),
        );

        return $this->jsonResponse(['data' => ['count' => $count]]);
    }
}
