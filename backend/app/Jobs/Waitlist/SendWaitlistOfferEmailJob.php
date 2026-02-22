<?php

namespace HiEvents\Jobs\Waitlist;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Mail\Waitlist\WaitlistOfferMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
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
        EventRepositoryInterface      $eventRepository,
        ProductPriceRepositoryInterface $productPriceRepository,
        ProductRepositoryInterface    $productRepository,
        Mailer                        $mailer,
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

        $mailer
            ->to($this->entry->getEmail())
            ->locale($this->entry->getLocale())
            ->send(new WaitlistOfferMail(
                entry: $this->entry,
                event: $event,
                product: $product,
                productPrice: $productPrice,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                orderShortId: $this->orderShortId,
                sessionIdentifier: $this->sessionIdentifier,
            ));
    }
}
