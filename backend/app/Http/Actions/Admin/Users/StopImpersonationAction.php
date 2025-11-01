<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Users;

use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Services\Application\Handlers\Admin\DTO\StopImpersonationDTO;
use HiEvents\Services\Application\Handlers\Admin\StopImpersonationHandler;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;

class StopImpersonationAction extends BaseAuthAction
{
    public function __construct(
        private readonly StopImpersonationHandler $handler,
        private readonly AuthManager              $authManager,
    )
    {
    }

    public function __invoke(): JsonResponse
    {
        $isImpersonating = $this->authManager->payload()->get('is_impersonating');

        if (!$isImpersonating) {
            return $this->errorResponse(__('Not currently impersonating'));
        }

        $impersonatorId = $this->authManager->payload()->get('impersonator_id');

        $token = $this->handler->handle(new StopImpersonationDTO(
            impersonatorId: $impersonatorId,
        ));

        $response = $this->jsonResponse([
            'message' => __('Impersonation ended'),
            'redirect_url' => '/admin/users',
            'token' => $token
        ]);

        return $this->addTokenToResponse($response, $token);
    }
}
