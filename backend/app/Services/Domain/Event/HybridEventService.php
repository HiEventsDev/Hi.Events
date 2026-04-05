<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;

class HybridEventService
{
    public function getAttendeeConnectionDetails(
        ProductDomainObject $product,
        EventSettingDomainObject $eventSettings,
    ): array {
        $attendanceMode = $product->getAttendanceMode();

        return match ($attendanceMode) {
            'ONLINE' => [
                'type' => 'online',
                'meeting_url' => $product->getOnlineMeetingUrl()
                    ?: $eventSettings->getHybridStreamUrl()
                    ?: $eventSettings->getOnlineEventConnectionDetails(),
                'instructions' => $eventSettings->getOnlineEventConnectionDetails(),
            ],
            'HYBRID' => [
                'type' => 'hybrid',
                'meeting_url' => $product->getOnlineMeetingUrl()
                    ?: $eventSettings->getHybridStreamUrl(),
                'venue_instructions' => $product->getVenueInstructions()
                    ?: $eventSettings->getHybridVenueInstructions(),
                'location_details' => $eventSettings->getLocationDetails(),
                'online_connection_details' => $eventSettings->getOnlineEventConnectionDetails(),
            ],
            default => [ // IN_PERSON
                'type' => 'in_person',
                'venue_instructions' => $product->getVenueInstructions()
                    ?: $eventSettings->getHybridVenueInstructions(),
                'location_details' => $eventSettings->getLocationDetails(),
                'maps_url' => $eventSettings->getMapsUrl(),
            ],
        };
    }

    public function isHybridEvent(EventSettingDomainObject $eventSettings): bool
    {
        return $eventSettings->getEventLocationType() === 'HYBRID';
    }
}
