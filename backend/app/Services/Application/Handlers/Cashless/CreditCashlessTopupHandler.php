<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessTopupDomainObject;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\CashlessTopupDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\CashlessTopupStatus;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessTopupRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;

class CreditCashlessTopupHandler
{
    public function __construct(
        private readonly CashlessTopupRepositoryInterface $topupRepository,
        private readonly CashlessWalletRepositoryInterface $walletRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CashlessWalletService $walletService,
        private readonly MailBuilderService $mailBuilderService,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(OrderDomainObject $order): void
    {
        $topup = $this->topupRepository->findFirstWhere([
            CashlessTopupDomainObjectAbstract::ORDER_ID => $order->getId(),
            CashlessTopupDomainObjectAbstract::STATUS => CashlessTopupStatus::PENDING->value,
        ]);

        if ($topup === null) {
            return;
        }

        $transaction = $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $topup->getCashlessWalletId(),
            type: CashlessTransactionType::TOPUP_ONLINE,
            positive_amount: $topup->getAmount(),
            order_id: $order->getId(),
        ));

        $this->markAsCredited($topup, $transaction);

        $this->sendConfirmation($topup, $transaction, $order);
    }

    private function markAsCredited(CashlessTopupDomainObject $topup, CashlessTransactionDomainObject $transaction): void
    {
        $this->topupRepository->updateWhere(
            attributes: [
                CashlessTopupDomainObjectAbstract::STATUS => CashlessTopupStatus::CREDITED->value,
                CashlessTopupDomainObjectAbstract::CASHLESS_TRANSACTION_ID => $transaction->getId(),
            ],
            where: [
                CashlessTopupDomainObjectAbstract::ID => $topup->getId(),
                CashlessTopupDomainObjectAbstract::STATUS => CashlessTopupStatus::PENDING->value,
            ],
        );
    }

    private function sendConfirmation(
        CashlessTopupDomainObject $topup,
        CashlessTransactionDomainObject $transaction,
        OrderDomainObject $order,
    ): void {
        /** @var CashlessWalletDomainObject $wallet */
        $wallet = $this->walletRepository->findById($topup->getCashlessWalletId());

        /** @var AttendeeDomainObject $attendee */
        $attendee = $this->attendeeRepository->findById($wallet->getAttendeeId());

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($wallet->getEventId());

        $this->mailer
            ->to($attendee->getEmail())
            ->locale($order->getLocale())
            ->send($this->mailBuilderService->buildCashlessTopupConfirmationMail(
                wallet: $wallet,
                transaction: $transaction,
                attendee: $attendee,
                event: $event,
                eventSettings: $event->getEventSettings(),
                organizer: $event->getOrganizer(),
            ));
    }
}
