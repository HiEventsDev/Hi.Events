<?php

namespace HiEvents\Services\Application\Handlers\Account\Sso;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\DTO\SsoEnforcementPreview;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\SsoEnforcementService;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\SsoSettingsChangeNotifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

class SsoEnforcementHandler
{
    public function __construct(
        private readonly SsoEnforcementService $enforcementService,
        private readonly DatabaseManager $databaseManager,
        private readonly SsoSettingsChangeNotifier $changeNotifier,
    ) {}

    public function preview(int $accountId): SsoEnforcementPreview
    {
        return $this->enforcementService->preview($accountId);
    }

    /**
     * @param  int[]  $exemptAccountUserIds
     *
     * @throws AccountSsoConfigurationException
     * @throws Throwable
     */
    public function update(int $accountId, int $actorUserId, bool $enabled, array $exemptAccountUserIds): AccountSsoConnectionDomainObject
    {
        $connection = $this->enforcementService->findConnection($accountId)
            ?? throw new AccountSsoConfigurationException(__('Set up a single sign-on connection first.'), 'enforce_sso');

        if (! $enabled) {
            $updated = $this->enforcementService->disable($connection);
            $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Stopped requiring single sign-on'));

            return $updated;
        }

        $updated = $this->databaseManager->transaction(
            fn () => $this->enforcementService->enable($connection, $exemptAccountUserIds),
        );
        $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Required single sign-on for members on verified domains'));

        return $updated;
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function setMemberEnforced(int $accountId, int $accountUserId, bool $enforced): void
    {
        $this->enforcementService->setMemberEnforced($accountId, $accountUserId, $enforced);
    }
}
