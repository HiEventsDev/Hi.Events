<?php

namespace HiEvents\Jobs\Waitlist;

use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Mail\Waitlist\WaitlistConfirmationMail;
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

class SendWaitlistConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly WaitlistEntryDomainObject $entry,
    )
    {
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

        $mail = new WaitlistConfirmationMail(
            entry: $this->entry,
            event: $event,
            product: $product,
            productPrice: $productPrice,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
        );

        $trackingService->recordAndSend(
            mailer: $mailer,
            recipient: $this->entry->getEmail(),
            mail: $mail,
            emailType: TransactionalEmailType::WAITLIST_CONFIRMATION,
            subject: $mail->envelope()->subject,
            eventId: $event->getId(),
            accountId: $event->getAccountId(),
            locale: $this->entry->getLocale(),
        );
    }
}
