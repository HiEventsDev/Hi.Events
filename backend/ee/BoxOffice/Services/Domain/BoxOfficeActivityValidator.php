<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Config\Repository;

class BoxOfficeActivityValidator
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Repository $config,
    ) {}

    /**
     * @throws CannotSellException
     */
    public function assertActive(BoxOfficeDomainObject $boxOffice, EventDomainObject $event): void
    {
        if ($boxOffice->getExpiresAt() && DateHelper::utcDateIsPast($boxOffice->getExpiresAt())) {
            throw new CannotSellException(__('This box office has expired'));
        }

        if ($boxOffice->getActivatesAt() && DateHelper::utcDateIsFuture($boxOffice->getActivatesAt())) {
            throw new CannotSellException(__('This box office is not active yet'));
        }

        $this->assertEventSellable($event);
    }

    /**
     * @throws CannotSellException
     */
    private function assertEventSellable(EventDomainObject $event): void
    {
        if (in_array($event->getStatus(), [EventStatus::ARCHIVED->name, EventStatus::PENDING_MANUAL_REVIEW->name], true)) {
            throw new CannotSellException(__('This event is not accepting sales'));
        }

        if ($event->getStatus() !== EventStatus::DRAFT->name || ! $this->config->get('app.saas_mode_enabled')) {
            return;
        }

        $account = $this->accountRepository->findById($event->getAccountId());

        if ($account->getAccountVerifiedAt() === null) {
            throw new CannotSellException(__('Verify your account before selling tickets for an unpublished event'));
        }
    }
}
