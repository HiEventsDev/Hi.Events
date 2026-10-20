<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Mail\SsoBreakGlassSignInMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Psr\Log\LoggerInterface;

class SsoBreakGlassService
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Mailer $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(UserDomainObject $owner, int $accountId): void
    {
        $signedInAt = now()->utc();

        $this->logger->warning('SSO break-glass sign-in', [
            'account_id' => $accountId,
            'user_id' => $owner->getId(),
            'ip' => request()?->ip(),
            'signed_in_at' => $signedInAt->toIso8601String(),
        ]);

        $accountName = $this->accountRepository->findById($accountId)->getName();

        $this->accountUserRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'user'))
            ->findWhere([
                'account_id' => $accountId,
                'role' => Role::ADMIN->name,
                'status' => UserStatus::ACTIVE->name,
            ])
            ->each(function (AccountUserDomainObject $admin) use ($owner, $accountName, $signedInAt) {
                $recipient = $admin->getUser();

                if ($recipient === null) {
                    return;
                }

                $this->mailer
                    ->to($recipient->getEmail())
                    ->locale($recipient->getLocale())
                    ->send(new SsoBreakGlassSignInMail(
                        ownerEmail: $owner->getEmail(),
                        accountName: $accountName,
                        signedInAt: $signedInAt->format('Y-m-d H:i'),
                        recipientIsOwner: $recipient->getId() === $owner->getId(),
                    ));
            });
    }
}
