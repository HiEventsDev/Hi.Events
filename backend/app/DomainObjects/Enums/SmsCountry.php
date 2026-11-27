<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum SmsCountry: string
{
    use BaseEnum;

    case US = 'US';
    case GB = 'GB';
    case IE = 'IE';
    case AU = 'AU';

    private const FIRST_SENDING_HOUR = 9;

    private const LAST_SENDING_HOUR = 20;

    private const WINDOW_SEARCH_STEP_MINUTES = 15;

    private const WINDOW_SEARCH_LIMIT_HOURS = 48;

    public function optsOutByReply(): bool
    {
        return $this === self::US;
    }

    /**
     * @return string[]
     */
    public function timezones(): array
    {
        return match ($this) {
            self::US => ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu'],
            self::GB => ['Europe/London'],
            self::IE => ['Europe/Dublin'],
            self::AU => ['Australia/Sydney', 'Australia/Brisbane', 'Australia/Adelaide', 'Australia/Darwin', 'Australia/Perth'],
        };
    }

    public function allowsSendingAt(CarbonInterface $moment): bool
    {
        foreach ($this->timezones() as $timezone) {
            $hour = CarbonImmutable::instance($moment)->setTimezone($timezone)->hour;

            if ($hour < self::FIRST_SENDING_HOUR || $hour >= self::LAST_SENDING_HOUR) {
                return false;
            }
        }

        return true;
    }

    public function nextSendingTime(CarbonInterface $from): CarbonImmutable
    {
        if ($this->allowsSendingAt($from)) {
            return CarbonImmutable::instance($from);
        }

        $start = CarbonImmutable::instance($from)->startOfMinute();
        $candidate = $start->addMinutes((self::WINDOW_SEARCH_STEP_MINUTES - $start->minute % self::WINDOW_SEARCH_STEP_MINUTES) % self::WINDOW_SEARCH_STEP_MINUTES);
        $limit = $candidate->addHours(self::WINDOW_SEARCH_LIMIT_HOURS);

        while ($candidate->lessThan($limit)) {
            if ($this->allowsSendingAt($candidate)) {
                return $candidate;
            }

            $candidate = $candidate->addMinutes(self::WINDOW_SEARCH_STEP_MINUTES);
        }

        return $limit;
    }

    /**
     * @param  string[]  $countries
     * @return string[]
     */
    public static function sendableNow(array $countries, CarbonInterface $now): array
    {
        return array_values(array_filter(
            $countries,
            static fn (string $country) => self::tryFrom($country)?->allowsSendingAt($now) ?? false,
        ));
    }
}
