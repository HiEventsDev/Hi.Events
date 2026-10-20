<?php

namespace HiEvents\Services\Application\Handlers\ApiKey;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\ApiKey\DTO\CreateApiKeyDTO;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use HiEvents\Services\Domain\Auth\ApiKey\DTO\CreatedApiKeyDTO;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;

class CreateApiKeyHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly IdentityConfirmationService $identityConfirmationService,
        private readonly ApiKeyService $apiKeyService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws PermissionRequiredException
     * @throws IdentityConfirmationFailedException
     */
    public function handle(CreateApiKeyDTO $dto): CreatedApiKeyDTO
    {
        $user = $this->userRepository->findById($dto->userId);

        if ($user->getEmailVerifiedAt() === null) {
            throw new ResourceConflictException(__('Confirm your email address before creating an API key.'));
        }

        if ($dto->access === ApiKeyAccess::READ_WRITE && ! $dto->canEdit) {
            throw new PermissionRequiredException(__('You have view-only access, so you can only create read-only API keys.'));
        }

        if ($this->apiKeyService->countActiveKeys($dto->userId, $dto->accountId) >= ApiKeyService::MAX_ACTIVE_KEYS_PER_MEMBER) {
            throw new ResourceConflictException(__(
                'You can have up to :max active API keys. Revoke one you no longer use and try again.',
                ['max' => ApiKeyService::MAX_ACTIVE_KEYS_PER_MEMBER],
            ));
        }

        $this->identityConfirmationService->confirm($user, $dto->password, $dto->reauthProof, $dto->tokenId);

        return $this->apiKeyService->create(
            user: $user,
            accountId: $dto->accountId,
            name: $dto->name,
            access: $dto->access,
            expiresInDays: $dto->expiresInDays,
        );
    }
}
