<?php

namespace HiEvents\Services\Domain\Federation;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;

/**
 * Transforms Hi.Events domain objects into ActivityPub-compatible JSON-LD
 * for event federation across instances and with Fediverse platforms (Mobilizon, etc.).
 */
class ActivityPubTransformer
{
    /**
     * Transform an organizer into an ActivityPub Actor (Organization type).
     */
    public function organizerToActor(OrganizerDomainObject $organizer, string $baseUrl): array
    {
        $actorId = sprintf('%s/federation/actors/organizers/%d', $baseUrl, $organizer->getId());

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $actorId,
            'type' => 'Organization',
            'name' => $organizer->getName(),
            'summary' => $organizer->getDescription() ?? '',
            'url' => sprintf('%s/o/%s', $baseUrl, $organizer->getSlug()),
            'inbox' => $actorId . '/inbox',
            'outbox' => $actorId . '/outbox',
            'followers' => $actorId . '/followers',
            'preferredUsername' => $organizer->getSlug(),
        ];
    }

    /**
     * Transform an event into an ActivityPub Object (Event type).
     */
    public function eventToObject(
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        ?EventSettingDomainObject $settings,
        string                   $baseUrl,
    ): array
    {
        $eventId = sprintf('%s/federation/events/%d', $baseUrl, $event->getId());
        $actorId = sprintf('%s/federation/actors/organizers/%d', $baseUrl, $organizer->getId());

        $object = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $eventId,
            'type' => 'Event',
            'name' => $event->getTitle(),
            'url' => $event->getEventUrl(),
            'attributedTo' => $actorId,
            'published' => $event->getCreatedAt(),
            'updated' => $event->getUpdatedAt(),
        ];

        if ($event->getDescription()) {
            $object['content'] = $event->getDescription();
            $object['mediaType'] = 'text/html';
        }

        if ($event->getStartDate()) {
            $object['startTime'] = $event->getStartDate();
        }

        if ($event->getEndDate()) {
            $object['endTime'] = $event->getEndDate();
        }

        if ($settings?->getLocationDetails()) {
            $location = $settings->getLocationDetails();
            $object['location'] = [
                'type' => 'Place',
                'name' => $location['venue_name'] ?? '',
                'address' => [
                    'type' => 'PostalAddress',
                    'streetAddress' => $location['address_line_1'] ?? '',
                    'addressLocality' => $location['city'] ?? '',
                    'addressRegion' => $location['state_or_region'] ?? '',
                    'postalCode' => $location['zip_or_postal_code'] ?? '',
                    'addressCountry' => $location['country'] ?? '',
                ],
            ];
        }

        return $object;
    }

    /**
     * Wrap an event in a Create activity for federation.
     */
    public function wrapInCreateActivity(array $eventObject, string $actorId): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $eventObject['id'] . '/activity',
            'type' => 'Create',
            'actor' => $actorId,
            'published' => $eventObject['published'] ?? now()->toIso8601String(),
            'object' => $eventObject,
        ];
    }
}
