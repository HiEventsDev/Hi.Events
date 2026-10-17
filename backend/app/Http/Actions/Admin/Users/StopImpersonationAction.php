<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Users;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Admin\DTO\StopImpersonationDTO;
use HiEvents\Services\Application\Handlers\Admin\StopImpersonationHandler;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;

class StopImpersonationAction extends BaseAuthAction
{
    public function __construct(
        private readonly StopImpersonationHandler $handler,
        private readonly AuthManager $authManager,
    ) {}

    public function __invoke(): JsonResponse
    {
        $isImpersonating = $this->authManager->payload()->get('is_impersonating');

        if (! $isImpersonating) {
            return $this->errorResponse(__('Not currently impersonating'));
        }

        $impersonatorId = $this->authManager->payload()->get('impersonator_id');

        try {
            $token = $this->handler->handle(new StopImpersonationDTO(
                impersonatorId: $impersonatorId,
            ));
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_FORBIDDEN);
        }

        $response = $this->jsonResponse([
            'message' => __('Impersonation ended'),
            'redirect_url' => '/admin/users',
            'token' => $token,
        ]);

        return $this->addTokenToResponse($response, $token);
    }
}
