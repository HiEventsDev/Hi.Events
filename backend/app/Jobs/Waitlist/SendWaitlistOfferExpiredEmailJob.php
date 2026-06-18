<?php

namespace HiEvents\Jobs\Waitlist;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Mail\Waitlist\WaitlistOfferExpiredMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWaitlistOfferExpiredEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly WaitlistEntryDomainObject $entry,
    ) {}

    public function handle(
        EventRepositoryInterface $eventRepository,
        ProductPriceRepositoryInterface $productPriceRepository,
        ProductRepositoryInterface $productRepository,
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        Mailer $mailer,
        MailBrandingService $mailBrandingService,
    ): void {
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

        $occurrence = $this->entry->getEventOccurrenceId() !== null
            ? $occurrenceRepository->findById($this->entry->getEventOccurrenceId())
            : null;

        $mail = new WaitlistOfferExpiredMail(
            entry: $this->entry,
            event: $event,
            product: $product,
            productPrice: $productPrice,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            occurrence: $occurrence,
        );

        $mailer
            ->to($this->entry->getEmail())
            ->locale($this->entry->getLocale())
            ->send($mail->withBranding($mailBrandingService->resolveForEvent($event->getId())));
    }
}
