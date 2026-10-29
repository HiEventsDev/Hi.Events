<?php

namespace HiEvents\DomainObjects\Enums;

enum HomepageSectionType: string
{
    use BaseEnum;

    case TICKETS = 'TICKETS';
    case ABOUT = 'ABOUT';
    case LINEUP = 'LINEUP';
    case SCHEDULE = 'SCHEDULE';
    case LOCATION = 'LOCATION';
    case ORGANIZER = 'ORGANIZER';
    case MORE_EVENTS = 'MORE_EVENTS';
    case EVENTS = 'EVENTS';
    case CONTACT = 'CONTACT';
    case PAST_EVENTS = 'PAST_EVENTS';
    case TEXT = 'TEXT';
    case GALLERY = 'GALLERY';
    case FAQ = 'FAQ';
    case VIDEO = 'VIDEO';
    case SPONSORS = 'SPONSORS';

    /**
     * @return self[]
     */
    public static function eventBuiltIns(): array
    {
        return [self::TICKETS, self::ABOUT, self::LINEUP, self::SCHEDULE, self::LOCATION, self::ORGANIZER, self::MORE_EVENTS];
    }

    /**
     * @return self[]
     */
    public static function organizerBuiltIns(): array
    {
        return [self::EVENTS, self::ABOUT, self::CONTACT, self::PAST_EVENTS];
    }

    /**
     * @return self[]
     */
    public static function customTypes(): array
    {
        return [self::TEXT, self::GALLERY, self::FAQ, self::VIDEO, self::SPONSORS];
    }

    /**
     * @return string[]
     */
    public static function eventValues(): array
    {
        return array_map(static fn (self $type) => $type->value, [...self::eventBuiltIns(), ...self::customTypes()]);
    }

    /**
     * @return string[]
     */
    public static function organizerValues(): array
    {
        return array_map(static fn (self $type) => $type->value, [...self::organizerBuiltIns(), ...self::customTypes()]);
    }

    public function isEnabledIn(mixed $sections): bool
    {
        if (! is_array($sections)) {
            return true;
        }

        foreach ($sections as $section) {
            if (($section['type'] ?? null) === $this->value) {
                return (bool) ($section['enabled'] ?? true);
            }
        }

        return true;
    }

    public static function publicSections(mixed $sections): mixed
    {
        if (! is_array($sections)) {
            return $sections;
        }

        return array_values(array_filter(
            $sections,
            static fn ($section) => ($section['enabled'] ?? true)
                || ! in_array(self::tryFrom($section['type'] ?? ''), self::customTypes(), true),
        ));
    }

    public static function isVideoUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m)\./', '', $host);

        return in_array($host, ['youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com'], true);
    }
}
