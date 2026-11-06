<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\OAuth\ApproveOAuthAuthorizationRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\OAuth\DTO\ApproveOAuthAuthorizationDTO;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConsentHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
class ApproveOAuthAuthorizationRequestAction extends BaseAction
{
    public function __construct(
        private readonly OAuthConsentHandler $handler,
    ) {}

    public function __invoke(string $requestId, ApproveOAuthAuthorizationRequest $request): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('Apps cannot be connected while impersonating a user.'));
        }

        try {
            $redirectUrl = $this->handler->approve(new ApproveOAuthAuthorizationDTO(
                requestId: $requestId,
                userId: $this->getAuthenticatedUser()->getId(),
                accountId: $this->getAuthenticatedAccountId(),
                access: ApiKeyAccess::from($request->validated('access')),
                canEdit: $this->getAuthenticatedAccess()->can(Permission::EDIT),
                password: $request->validated('password'),
                reauthProof: $request->validated('reauth_proof'),
                tokenId: $this->getAuthTokenId(),
            ));
        } catch (IdentityConfirmationFailedException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->jsonResponse(['redirect_url' => $redirectUrl], wrapInData: true);
    }
}
