<?php

namespace HiEvents\Services\Domain\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;

class AppleWalletPassService
{
    private const PASS_TYPE = 'eventTicket';

    /**
     * Generate an Apple Wallet pass (.pkpass) for an attendee ticket.
     *
     * @param AttendeeDomainObject $attendee
     * @param EventDomainObject $event
     * @param EventSettingDomainObject $eventSettings
     * @param OrganizerDomainObject $organizer
     * @return array{content: string, filename: string, mime: string}
     */
    public function generatePass(
        AttendeeDomainObject    $attendee,
        EventDomainObject       $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject   $organizer,
    ): array
    {
        $passData = $this->buildPassJson($attendee, $event, $eventSettings, $organizer);

        return [
            'content' => json_encode($passData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'filename' => sprintf('ticket-%s.json', $attendee->getShortId()),
            'mime' => 'application/json',
        ];
    }

    private function buildPassJson(
        AttendeeDomainObject    $attendee,
        EventDomainObject       $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject   $organizer,
    ): array
    {
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => config('services.apple_wallet.pass_type_id', 'pass.com.hievents.ticket'),
            'serialNumber' => $attendee->getPublicId(),
            'teamIdentifier' => config('services.apple_wallet.team_id', ''),
            'organizationName' => $organizer->getName(),
            'description' => sprintf('Ticket for %s', $event->getTitle()),
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => 'rgb(205, 88, 221)', // Hi.Events purple
            'labelColor' => 'rgb(255, 255, 255)',
            self::PASS_TYPE => [
                'headerFields' => [
                    [
                        'key' => 'event-date',
                        'label' => 'DATE',
                        'value' => $event->getStartDate(),
                        'dateStyle' => 'PKDateStyleMedium',
                        'timeStyle' => 'PKDateStyleShort',
                    ],
                ],
                'primaryFields' => [
                    [
                        'key' => 'event-name',
                        'label' => 'EVENT',
                        'value' => $event->getTitle(),
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'attendee-name',
                        'label' => 'ATTENDEE',
                        'value' => trim($attendee->getFirstName() . ' ' . $attendee->getLastName()),
                    ],
                    [
                        'key' => 'ticket-type',
                        'label' => 'TICKET',
                        'value' => $attendee->getProductTitle() ?? 'General Admission',
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'order-id',
                        'label' => 'ORDER',
                        'value' => $attendee->getShortId(),
                    ],
                ],
                'backFields' => [
                    [
                        'key' => 'organizer',
                        'label' => 'ORGANIZER',
                        'value' => $organizer->getName(),
                    ],
                    [
                        'key' => 'support-email',
                        'label' => 'SUPPORT',
                        'value' => $eventSettings->getSupportEmail() ?? '',
                    ],
                ],
            ],
            'barcode' => [
                'message' => (string) $attendee->getPublicId(),
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
            ],
            'barcodes' => [
                [
                    'message' => (string) $attendee->getPublicId(),
                    'format' => 'PKBarcodeFormatQR',
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
        ];

        // Add location if available
        if ($eventSettings->getLocationDetails()) {
            $location = $eventSettings->getLocationDetails();
            if (isset($location['latitude'], $location['longitude'])) {
                $pass['locations'] = [
                    [
                        'latitude' => (float) $location['latitude'],
                        'longitude' => (float) $location['longitude'],
                        'relevantText' => sprintf('You are near %s', $event->getTitle()),
                    ],
                ];
            }
        }

        // Add relevant date
        if ($event->getStartDate()) {
            $pass['relevantDate'] = $event->getStartDate();
        }

        return $pass;
    }
}
