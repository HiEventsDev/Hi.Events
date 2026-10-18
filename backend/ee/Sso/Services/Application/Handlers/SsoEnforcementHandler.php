<?php

namespace HiEvents\Enterprise\Sso\Services\Application\Handlers;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\SsoEnforcementPreview;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Enterprise\Sso\Services\Domain\SsoSettingsChangeNotifier;
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
            $this->changeNotifier->notifyOwner($accountId, $actorUserId, 'Stopped requiring single sign-on');

            return $updated;
        }

        $updated = $this->databaseManager->transaction(
            fn () => $this->enforcementService->enable($connection, $exemptAccountUserIds),
        );
        $this->changeNotifier->notifyOwner($accountId, $actorUserId, 'Required single sign-on for members on verified domains');

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
