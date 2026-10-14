<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Admin\ResetUserTwoFactorHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ResetUserTwoFactorAction extends BaseAction
{
    public function __construct(
        private readonly ResetUserTwoFactorHandler $handler,
    ) {}

    public function __invoke(int $userId): Response|JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        try {
            $this->handler->handle($userId, $this->getAuthenticatedUser()->getId());
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
