<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Contact\IgnoreAttendeesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IgnoreAttendeesAction extends BaseAction
{
    public function __construct(
        private readonly IgnoreAttendeesHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $validated = $request->validate([
            'attendee_ids' => ['required', 'array', 'min:1'],
            'attendee_ids.*' => ['required', 'integer'],
        ]);

        $count = $this->handler->handle(
            accountId: $this->getAuthenticatedAccountId(),
            attendeeIds: $validated['attendee_ids'],
        );

        return $this->jsonResponse(['data' => ['count' => $count]]);
    }
}
