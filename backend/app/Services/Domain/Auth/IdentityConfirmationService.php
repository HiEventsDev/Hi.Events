<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use Illuminate\Contracts\Hashing\Hasher;

class IdentityConfirmationService
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly SsoTicketService $ticketService,
        private readonly PasswordLoginPolicy $passwordLoginPolicy,
    ) {}

    /**
     * @throws IdentityConfirmationFailedException
     */
    public function confirm(UserDomainObject $user, ?string $password, ?string $reauthProof, ?string $tokenId): void
    {
        if ($this->passwordLoginPolicy->canConfirmWithPassword($user)) {
            if ($password === null || ! $this->hasher->check($password, $user->getPassword())) {
                throw new IdentityConfirmationFailedException(__('The password is incorrect.'));
            }

            return;
        }

        if ($reauthProof === null || ! $this->isValidProof($reauthProof, $user->getId(), $tokenId)) {
            throw new IdentityConfirmationFailedException(__('Please confirm it\'s you and try again.'));
        }
    }

    private function isValidProof(string $reauthProof, int $userId, ?string $tokenId): bool
    {
        try {
            $payload = $this->ticketService->find(SsoTicketType::REAUTH, $reauthProof);
        } catch (SsoException) {
            return false;
        }

        return ($payload['user_id'] ?? null) === $userId
            && $tokenId !== null
            && hash_equals((string) ($payload['token_id'] ?? ''), $tokenId);
    }
}
