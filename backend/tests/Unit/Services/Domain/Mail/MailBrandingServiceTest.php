<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Branding\BrandingVisibilityService;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class MailBrandingServiceTest extends TestCase
{
    public function test_from_event_returns_unbranded_dto_when_branding_visible(): void
    {
        $visibilityService = Mockery::mock(BrandingVisibilityService::class);
        $visibilityService->shouldReceive('shouldHideBranding')->andReturn(false);

        $service = new MailBrandingService($visibilityService, Mockery::mock(EventRepositoryInterface::class));

        $branding = $service->fromEvent(new EventDomainObject());

        $this->assertFalse($branding->hideBranding);
        $this->assertNull($branding->organizerLogoUrl);
        $this->assertNull($branding->organizerName);
    }

    public function test_from_event_returns_organizer_logo_and_name_when_branding_hidden(): void
    {
        $logo = (new ImageDomainObject())
            ->setType(ImageType::ORGANIZER_LOGO->name)
            ->setPath('logos/organizer.png');

        $organizer = (new OrganizerDomainObject())
            ->setName('Acme Events')
            ->setOrganizerSettings(new OrganizerSettingDomainObject())
            ->setImages(new Collection([$logo]));

        $event = (new EventDomainObject())->setOrganizer($organizer);

        $visibilityService = Mockery::mock(BrandingVisibilityService::class);
        $visibilityService->shouldReceive('shouldHideBranding')->andReturn(true);

        $service = new MailBrandingService($visibilityService, Mockery::mock(EventRepositoryInterface::class));

        $branding = $service->fromEvent($event);

        $this->assertTrue($branding->hideBranding);
        $this->assertStringContainsString('logos/organizer.png', $branding->organizerLogoUrl);
        $this->assertSame('Acme Events', $branding->organizerName);
    }

    public function test_resolve_for_event_memoizes_lookups(): void
    {
        $visibilityService = Mockery::mock(BrandingVisibilityService::class);
        $visibilityService->shouldReceive('shouldHideBranding')->andReturn(false);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findFirst')->once()->with(99)->andReturn(new EventDomainObject());

        $service = new MailBrandingService($visibilityService, $eventRepository);

        $first = $service->resolveForEvent(99);
        $second = $service->resolveForEvent(99);

        $this->assertSame($first, $second);
    }

    public function test_resolve_for_event_returns_unbranded_dto_when_event_missing(): void
    {
        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findFirst')->andReturnNull();

        $service = new MailBrandingService(Mockery::mock(BrandingVisibilityService::class), $eventRepository);

        $branding = $service->resolveForEvent(42);

        $this->assertFalse($branding->hideBranding);
        $this->assertNull($branding->organizerLogoUrl);
    }
}
