<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Contact\IgnoreQuestionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IgnoreQuestionsAction extends BaseAction
{
    public function __construct(
        private readonly IgnoreQuestionsHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer'],
        ]);

        $count = $this->handler->handle(
            accountId: $this->getAuthenticatedAccountId(),
            questionIds: $validated['question_ids'],
        );

        return $this->jsonResponse(['data' => ['count' => $count]]);
    }
}
