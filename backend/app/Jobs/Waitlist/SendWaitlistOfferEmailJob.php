<?php

namespace HiEvents\Jobs\Waitlist;

use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Mail\Waitlist\WaitlistOfferMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Email\TransactionalEmailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWaitlistOfferEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly WaitlistEntryDomainObject $entry,
        private readonly string                    $orderShortId,
        private readonly string                    $sessionIdentifier,
    )
    {
        $this->afterCommit = true;
    }

    public function handle(
        EventRepositoryInterface           $eventRepository,
        ProductPriceRepositoryInterface    $productPriceRepository,
        ProductRepositoryInterface         $productRepository,
        Mailer                             $mailer,
        TransactionalEmailTrackingService  $trackingService,
    ): void
    {
        $event = $eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($this->entry->getEventId());

        $product = null;
        $productPrice = null;
        if ($this->entry->getProductPriceId()) {
            $productPrice = $productPriceRepository->findById($this->entry->getProductPriceId());
            $product = $productRepository->findById($productPrice->getProductId());
        }

        $mail = new WaitlistOfferMail(
            entry: $this->entry,
            event: $event,
            product: $product,
            productPrice: $productPrice,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            orderShortId: $this->orderShortId,
            sessionIdentifier: $this->sessionIdentifier,
        );

        $trackingService->recordAndSend(
            mailer: $mailer,
            recipient: $this->entry->getEmail(),
            mail: $mail,
            emailType: TransactionalEmailType::WAITLIST_OFFER,
            subject: $mail->envelope()->subject,
            eventId: $event->getId(),
            accountId: $event->getAccountId(),
            locale: $this->entry->getLocale(),
        );
    }
}
