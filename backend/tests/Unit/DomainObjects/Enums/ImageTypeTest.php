<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use Tests\TestCase;

class ImageTypeTest extends TestCase
{
    public function test_homepage_section_images_belong_to_their_page_owner(): void
    {
        $this->assertSame(EventDomainObject::class, ImageType::EVENT_GALLERY->getEntityType());
        $this->assertSame(EventDomainObject::class, ImageType::EVENT_SPONSOR_LOGO->getEntityType());
        $this->assertSame(OrganizerDomainObject::class, ImageType::ORGANIZER_GALLERY->getEntityType());
        $this->assertSame(OrganizerDomainObject::class, ImageType::ORGANIZER_SPONSOR_LOGO->getEntityType());
    }
}
