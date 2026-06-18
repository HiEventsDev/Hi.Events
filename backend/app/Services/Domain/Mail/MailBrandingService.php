<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Branding\BrandingVisibilityService;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;

class MailBrandingService
{
    /** @var array<int, MailBrandingDTO> */
    private array $resolvedByEventId = [];

    public function __construct(
        private readonly BrandingVisibilityService $brandingVisibilityService,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function resolveForEvent(int $eventId): MailBrandingDTO
    {
        return $this->resolvedByEventId[$eventId] ??= $this->fetchAndResolve($eventId);
    }

    public function fromEvent(EventDomainObject $event): MailBrandingDTO
    {
        $hideBranding = $this->brandingVisibilityService->shouldHideBranding(
            $event,
            $event->getOrganizer()?->getOrganizerSettings(),
        );

        if (! $hideBranding) {
            return new MailBrandingDTO(hideBranding: false, organizerLogoUrl: null, organizerName: null);
        }

        return new MailBrandingDTO(
            hideBranding: true,
            organizerLogoUrl: $this->getOrganizerLogoUrl($event->getOrganizer()),
            organizerName: $event->getOrganizer()?->getName(),
        );
    }

    private function fetchAndResolve(int $eventId): MailBrandingDTO
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(domainObject: OrganizerSettingDomainObject::class, name: 'organizer_settings'),
                    new Relationship(domainObject: ImageDomainObject::class),
                ],
                name: 'organizer',
            ))
            ->loadRelation(ProductDomainObject::class)
            ->findFirst($eventId);

        if ($event === null) {
            return new MailBrandingDTO(hideBranding: false, organizerLogoUrl: null, organizerName: null);
        }

        return $this->fromEvent($event);
    }

    private function getOrganizerLogoUrl(?OrganizerDomainObject $organizer): ?string
    {
        $logo = $organizer?->getImages()?->first(
            fn (ImageDomainObject $image) => $image->getType() === ImageType::ORGANIZER_LOGO->name
        );

        return $logo ? Url::getCdnUrl($logo->getPath()) : null;
    }
}
