<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\User\TwoFactor\BeginTwoFactorSetupRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\User\TwoFactor\TwoFactorSetupResource;
use HiEvents\Services\Application\Handlers\User\TwoFactor\BeginTwoFactorSetupHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
class BeginTwoFactorSetupAction extends BaseAction
{
    public function __construct(
        private readonly BeginTwoFactorSetupHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(BeginTwoFactorSetupRequest $request): JsonResponse
    {
        try {
            $setup = $this->handler->handle(
                userId: $this->getAuthenticatedUser()->getId(),
                password: $request->validated('password'),
                reauthProof: $request->validated('reauth_proof'),
                tokenId: $this->getAuthTokenId(),
            );
        } catch (IdentityConfirmationFailedException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(TwoFactorSetupResource::class, $setup);
    }
}
