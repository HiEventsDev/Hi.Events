<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts\DeletionRequest;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountDeletionRequestResource;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\GetAccountDeletionStatusHandler;
use Illuminate\Http\JsonResponse;

class GetAccountDeletionStatusAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountDeletionStatusHandler $getAccountDeletionStatusHandler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $status = $this->getAccountDeletionStatusHandler->handle($this->getAuthenticatedAccountId());

        return $this->jsonResponse([
            'data' => [
                'deletion_request' => $status->activeRequest
                    ? (new AccountDeletionRequestResource($status->activeRequest))->toArray(request())
                    : null,
                'can_request_deletion' => $status->canRequestDeletion,
                'cannot_delete_reason' => $status->cannotDeleteReason,
                'expected_outcome' => $status->expectedOutcome,
            ],
        ]);
    }
}
