<?php

namespace HiEvents\Services\Application\Handlers\OAuth;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\OAuth\DTO\ApproveOAuthAuthorizationDTO;
use HiEvents\Services\Application\Handlers\OAuth\DTO\OAuthConsentDTO;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationCodeDTO;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationRequestDTO;
use HiEvents\Services\Domain\Auth\OAuth\OAuthAuthorizationStore;
use HiEvents\Services\Domain\Auth\OAuth\OAuthEndpoints;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use HiEvents\Services\Domain\Auth\OAuth\OAuthTokenService;

class OAuthConsentHandler
{
    public function __construct(
        private readonly OAuthAuthorizationStore $store,
        private readonly OAuthEndpoints $endpoints,
        private readonly OAuthRedirectUriPolicy $redirectUriPolicy,
        private readonly OauthClientRepositoryInterface $clientRepository,
        private readonly OAuthTokenService $tokenService,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly IdentityConfirmationService $identityConfirmationService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function show(string $requestId, int $accountId): OAuthConsentDTO
    {
        [$request, $client] = $this->pending($requestId);

        return new OAuthConsentDTO(
            request_id: $requestId,
            client_name: $client->getName(),
            redirect_host: $this->redirectUriPolicy->describe($request->redirect_uri),
            is_verified: $this->redirectUriPolicy->isVerified($client->getRedirectUris() ?? []),
            is_local: $this->redirectUriPolicy->describe($request->redirect_uri) === 'localhost',
            requested_access: $request->access,
            account_name: $this->accountRepository->findById($accountId)->getName(),
        );
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws PermissionRequiredException
     * @throws IdentityConfirmationFailedException
     */
    public function approve(ApproveOAuthAuthorizationDTO $dto): string
    {
        [$request, $client] = $this->pending($dto->requestId);
        $user = $this->userRepository->findById($dto->userId);

        if ($user->getEmailVerifiedAt() === null) {
            throw new ResourceConflictException(__('Confirm your email address before connecting an app.'));
        }

        if ($dto->access === ApiKeyAccess::READ_WRITE && ! $dto->canEdit) {
            throw new PermissionRequiredException(__('You have view-only access, so you can only give apps read-only access.'));
        }

        if ($this->tokenService->hasReachedConnectionLimit($dto->userId, $dto->accountId, $client->getId())) {
            throw new ResourceConflictException(OAuthTokenService::connectionLimitMessage());
        }

        $this->identityConfirmationService->confirm($user, $dto->password, $dto->reauthProof, $dto->tokenId);

        $this->claim($dto->requestId);

        $code = $this->store->issueCode(new AuthorizationCodeDTO(
            client_id: $request->client_id,
            redirect_uri: $request->redirect_uri,
            code_challenge: $request->code_challenge,
            user_id: $dto->userId,
            account_id: $dto->accountId,
            access: $request->access === ApiKeyAccess::READ_ONLY ? ApiKeyAccess::READ_ONLY : $dto->access,
            resource: $request->resource,
        ));

        return $this->endpoints->clientRedirect($request->redirect_uri, ['code' => $code, 'state' => $request->state]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function deny(string $requestId): string
    {
        [$request] = $this->pending($requestId);

        $this->claim($requestId);

        return $this->endpoints->clientRedirect($request->redirect_uri, [
            'error' => OAuthErrorCode::ACCESS_DENIED->value,
            'state' => $request->state,
        ]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function claim(string $requestId): void
    {
        if (! $this->store->claimRequest($requestId)) {
            throw $this->expired();
        }
    }

    private function expired(): ResourceNotFoundException
    {
        return new ResourceNotFoundException(__('This connection request has expired. Start again from your app.'));
    }

    /**
     * @return array{AuthorizationRequestDTO, OauthClientDomainObject}
     *
     * @throws ResourceNotFoundException
     */
    private function pending(string $requestId): array
    {
        $request = $this->store->findRequest($requestId);

        /** @var OauthClientDomainObject|null $client */
        $client = $request === null ? null : $this->clientRepository->findFirstWhere(['client_id' => $request->client_id]);

        if ($client === null) {
            throw $this->expired();
        }

        return [$request, $client];
    }
}
