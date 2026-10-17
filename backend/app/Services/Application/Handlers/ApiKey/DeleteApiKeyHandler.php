<?php

namespace HiEvents\Services\Application\Handlers\ApiKey;

use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Mail\User\ApiKeyRevokedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use HiEvents\Services\Application\Handlers\ApiKey\DTO\DeleteApiKeyDTO;
use Illuminate\Contracts\Mail\Mailer;

class DeleteApiKeyHandler
{
    public function __construct(
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(DeleteApiKeyDTO $dto): void
    {
        /** @var PersonalAccessTokenDomainObject|null $apiKey */
        $apiKey = $this->apiKeyRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'user'))
            ->findFirstWhere([
                PersonalAccessTokenDomainObject::ID => $dto->apiKeyId,
                PersonalAccessTokenDomainObject::ACCOUNT_ID => $dto->accountId,
            ]);

        $isOwnKey = $apiKey?->getTokenableId() === $dto->userId;

        if ($apiKey === null || (! $isOwnKey && ! $dto->canManageTeam)) {
            throw new ResourceNotFoundException(__('API key not found'));
        }

        $this->apiKeyRepository->deleteById($apiKey->getId());

        $owner = $apiKey->getUser();

        if ($isOwnKey || $owner === null) {
            return;
        }

        $this->mailer
            ->to($owner->getEmail())
            ->locale($owner->getLocale())
            ->send(new ApiKeyRevokedMail(
                keyName: $apiKey->getName(),
                accountName: $this->accountRepository->findById($dto->accountId)->getName(),
            ));
    }
}
