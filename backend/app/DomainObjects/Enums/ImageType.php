<?php

namespace HiEvents\DomainObjects\Enums;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use InvalidArgumentException;

enum ImageType
{
    use BaseEnum;

    case GENERIC;

    // Event images
    case EVENT_COVER;
    case TICKET_LOGO;
    case EVENT_GALLERY;
    case EVENT_SPONSOR_LOGO;

    // Organizer images
    case ORGANIZER_LOGO;
    case ORGANIZER_COVER;
    case ORGANIZER_GALLERY;
    case ORGANIZER_SPONSOR_LOGO;

    case PROFILE_IMAGE;

    public static function eventImageTypes(): array
    {
        return [
            self::EVENT_COVER,
            self::TICKET_LOGO,
            self::EVENT_GALLERY,
            self::EVENT_SPONSOR_LOGO,
        ];
    }

    public static function organizerImageTypes(): array
    {
        return [
            self::ORGANIZER_LOGO,
            self::ORGANIZER_COVER,
            self::ORGANIZER_GALLERY,
            self::ORGANIZER_SPONSOR_LOGO,
        ];
    }

    public static function profileImageTypes(): array
    {
        return [
            self::PROFILE_IMAGE,
        ];
    }

    public static function genericImageTypes(): array
    {
        return [
            self::GENERIC,
        ];
    }

    public static function getMinimumDimensionsMap(ImageType $imageType): array
    {
        $map = [
            self::GENERIC->name => [50, 50],
            self::EVENT_COVER->name => [600, 50],
            self::TICKET_LOGO->name => [100, 100],
            self::ORGANIZER_LOGO->name => [100, 100],
            self::ORGANIZER_COVER->name => [600, 50],
            self::PROFILE_IMAGE->name => [200, 200],
            self::EVENT_GALLERY->name => [400, 300],
            self::EVENT_SPONSOR_LOGO->name => [80, 40],
            self::ORGANIZER_GALLERY->name => [400, 300],
            self::ORGANIZER_SPONSOR_LOGO->name => [80, 40],
        ];

        return $map[$imageType->name] ?? $map[self::GENERIC->name];
    }

    public function getEntityType(): string
    {
        if (in_array($this, self::eventImageTypes())) {
            return EventDomainObject::class;
        }

        if (in_array($this, self::organizerImageTypes())) {
            return OrganizerDomainObject::class;
        }

        if (in_array($this, self::profileImageTypes())) {
            return ProfileDomainObject::class;
        }

        if (in_array($this, self::genericImageTypes())) {
            return UserDomainObject::class;
        }

        throw new InvalidArgumentException('Invalid image type: '.$this->name);
    }
}
