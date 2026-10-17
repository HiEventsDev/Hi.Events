<?php

namespace HiEvents\Http\Actions\ApiKeys;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\ApiKey\CreateApiKeyRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\ApiKey\CreatedApiKeyResource;
use HiEvents\Services\Application\Handlers\ApiKey\CreateApiKeyHandler;
use HiEvents\Services\Application\Handlers\ApiKey\DTO\CreateApiKeyDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
class CreateApiKeyAction extends BaseAction
{
    public function __construct(
        private readonly CreateApiKeyHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateApiKeyRequest $request): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('API keys cannot be created while impersonating a user.'));
        }

        try {
            $created = $this->handler->handle(new CreateApiKeyDTO(
                userId: $this->getAuthenticatedUser()->getId(),
                accountId: $this->getAuthenticatedAccountId(),
                name: trim($request->validated('name')),
                access: ApiKeyAccess::from($request->validated('access')),
                expiresInDays: $request->validated('expires_in_days'),
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

        return $this->resourceResponse(CreatedApiKeyResource::class, $created, ResponseCodes::HTTP_CREATED);
    }
}
