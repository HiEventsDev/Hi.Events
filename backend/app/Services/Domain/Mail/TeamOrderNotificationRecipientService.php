<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;

class TeamOrderNotificationRecipientService
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserAccessService $accessService,
    ) {}

    /**
     * @return UserDomainObject[]
     */
    public function recipientsFor(EventDomainObject $event, ?string $excludeEmail): array
    {
        $subscribers = $this->accountUserRepository->findWhere([
            'account_id' => $event->getAccountId(),
            'notify_new_orders' => true,
            'status' => UserStatus::ACTIVE->name,
        ]);

        $this->accessService->preload($subscribers);

        $subscribers = $subscribers
            ->filter(function (AccountUserDomainObject $accountUser) use ($event) {
                $access = $this->accessService->forAccountUser($accountUser);

                return $access->canAccessEvent($event->getId(), $event->getOrganizerId())
                    && $access->can(Permission::VIEW_FINANCIALS);
            });

        if ($subscribers->isEmpty()) {
            return [];
        }

        $excluded = strtolower((string) $excludeEmail);

        return $this->userRepository
            ->findWhereIn(
                field: 'id',
                values: $subscribers->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUserId())->all(),
                additionalWhere: [['email_verified_at', 'not null', null]],
            )
            ->reject(fn (UserDomainObject $user) => strtolower($user->getEmail()) === $excluded)
            ->unique(fn (UserDomainObject $user) => strtolower($user->getEmail()))
            ->values()
            ->all();
    }
}
