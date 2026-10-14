<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\User\TwoFactor\ResetAccountUserTwoFactorHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ResetAccountUserTwoFactorAction extends BaseAction
{
    public function __construct(
        private readonly ResetAccountUserTwoFactorHandler $handler,
    ) {}

    public function __invoke(int $userId): Response|JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        try {
            $this->handler->handle(
                accountId: $this->getAuthenticatedAccountId(),
                targetUserId: $userId,
                actingUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
