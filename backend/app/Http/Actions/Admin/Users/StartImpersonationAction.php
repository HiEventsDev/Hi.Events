<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Services\Application\Handlers\Admin\DTO\StartImpersonationDTO;
use HiEvents\Services\Application\Handlers\Admin\StartImpersonationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartImpersonationAction extends BaseAuthAction
{
    public function __construct(
        private readonly StartImpersonationHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $userId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $this->validate($request, [
            'account_id' => 'required|exists:accounts,id'
        ]);

        $token = $this->handler->handle(new StartImpersonationDTO(
            userId: $userId,
            accountId: $request->input('account_id'),
            impersonatorId: $this->getAuthenticatedUser()->getId(),
        ));

        $response = $this->jsonResponse([
            'message' => __('Impersonation started'),
            'redirect_url' => '/manage/events',
            'token' => $token
        ]);

        return $this->addTokenToResponse($response, $token);
    }
}
