<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;

class LookupCashlessWalletHandler
{
    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletResolveService $walletResolveService,
    ) {}

    /**
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     */
    public function handle(int $eventId, string $attendeePublicId): CashlessWalletDomainObject
    {
        $this->cashlessSettingsService->getEnabledSettings($eventId);

        return $this->walletResolveService->resolveByAttendeePublicId($eventId, $attendeePublicId);
    }
}
