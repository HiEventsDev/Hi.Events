<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\LifecycleEmail;

use HiEvents\DomainObjects\Generated\UserDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailUnsubscribeTokens;

class UnsubscribeFromLifecycleEmailsHandler
{
    public function __construct(
        private readonly LifecycleEmailUnsubscribeTokens $tokens,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(string $token): void
    {
        $userId = $this->tokens->userIdFrom($token);

        if ($userId === null) {
            throw new ResourceNotFoundException;
        }

        $this->userRepository->updateWhere(
            attributes: [
                UserDomainObjectAbstract::LIFECYCLE_EMAILS_UNSUBSCRIBED_AT => now(),
                UserDomainObjectAbstract::MARKETING_OPTED_IN_AT => null,
            ],
            where: [UserDomainObjectAbstract::ID => $userId],
        );
    }
}
