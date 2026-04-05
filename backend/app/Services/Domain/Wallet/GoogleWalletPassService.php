<?php

namespace HiEvents\Services\Domain\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;

class GoogleWalletPassService
{
    /**
     * Generate a Google Wallet save link / JWT for an attendee ticket.
     *
     * @param AttendeeDomainObject $attendee
     * @param EventDomainObject $event
     * @param EventSettingDomainObject $eventSettings
     * @param OrganizerDomainObject $organizer
     * @return array{save_url: string, jwt: string}
     */
    public function generatePass(
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): array
    {
        $issuerId = config('services.google_wallet.issuer_id', '');
        $classId = sprintf('%s.event-%d', $issuerId, $event->getId());
        $objectId = sprintf('%s.attendee-%s', $issuerId, $attendee->getPublicId());

        $eventTicketClass = [
            'id' => $classId,
            'issuerName' => $organizer->getName(),
            'eventName' => [
                'defaultValue' => [
                    'language' => 'en-US',
                    'value' => $event->getTitle(),
                ],
            ],
            'reviewStatus' => 'UNDER_REVIEW',
        ];

        if ($event->getStartDate()) {
            $eventTicketClass['dateTime'] = [
                'start' => $event->getStartDate(),
            ];
            if ($event->getEndDate()) {
                $eventTicketClass['dateTime']['end'] = $event->getEndDate();
            }
        }

        if ($eventSettings->getLocationDetails()) {
            $location = $eventSettings->getLocationDetails();
            $venue = $location['venue_name'] ?? '';
            if ($venue) {
                $eventTicketClass['venue'] = [
                    'name' => [
                        'defaultValue' => [
                            'language' => 'en-US',
                            'value' => $venue,
                        ],
                    ],
                ];
                if (isset($location['address_line_1'])) {
                    $eventTicketClass['venue']['address'] = [
                        'defaultValue' => [
                            'language' => 'en-US',
                            'value' => $eventSettings->getAddressString(),
                        ],
                    ];
                }
            }
        }

        $eventTicketObject = [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'ticketHolderName' => trim($attendee->getFirstName() . ' ' . $attendee->getLastName()),
            'ticketNumber' => $attendee->getShortId(),
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => (string) $attendee->getPublicId(),
            ],
            'ticketType' => [
                'defaultValue' => [
                    'language' => 'en-US',
                    'value' => $attendee->getProductTitle() ?? 'General Admission',
                ],
            ],
        ];

        $claims = [
            'iss' => config('services.google_wallet.service_account_email', ''),
            'aud' => 'google',
            'origins' => [config('app.url')],
            'typ' => 'savetowallet',
            'payload' => [
                'eventTicketClasses' => [$eventTicketClass],
                'eventTicketObjects' => [$eventTicketObject],
            ],
        ];

        $jwtPayload = base64_encode(json_encode($claims));
        $saveUrl = sprintf('https://pay.google.com/gp/v/save/%s', $jwtPayload);

        return [
            'save_url' => $saveUrl,
            'jwt' => $jwtPayload,
            'class' => $eventTicketClass,
            'object' => $eventTicketObject,
        ];
    }
}
