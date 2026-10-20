<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class JoinInvitationHandler
{
    public function __construct(
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
        private readonly LoginService $loginService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     * @throws ResourceConflictException
     * @throws UnauthorizedException
     * @throws Throwable
     */
    public function handle(string $invitationToken, UserDomainObject $authenticatedUser, AuthContext $context): LoginResponse
    {
        return $this->databaseManager->transaction(function () use ($invitationToken, $authenticatedUser, $context) {
            $invitation = $this->invitationAcceptanceService->findPending($invitationToken);

            if ($invitation->user->getId() !== $authenticatedUser->getId()) {
                throw new ResourceConflictException(__('This invitation was sent to a different email address.'));
            }

            $this->invitationAcceptanceService->activate($invitation);

            return $this->loginService->completeLogin($invitation->user, $invitation->accountId, $context);
        });
    }
}
