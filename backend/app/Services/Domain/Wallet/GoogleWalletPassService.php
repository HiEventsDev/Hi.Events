<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Wallet;

use Firebase\JWT\JWT;
use HiEvents\DataTransferObjects\Wallet\TicketWalletPassData;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use Throwable;

class GoogleWalletPassService
{
    public function createSaveUrl(TicketWalletPassData $pass): string
    {
        $config = config('services.wallet_passes.google');
        $serviceAccountJson = base64_decode((string) $config['service_account'], true);
        $serviceAccount = $serviceAccountJson ? json_decode($serviceAccountJson, true) : null;

        if (! $config['issuer_id'] || ! is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            throw new WalletPassNotAvailableException(__('Google Wallet is not configured.'));
        }

        $issuerId = (string) $config['issuer_id'];
        $classId = $issuerId.'.event_'.$pass->eventId;
        $objectId = $issuerId.'.ticket_'.self::identifier($pass->serialNumber);
        $claims = [
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => [parse_url(config('app.frontend_url'), PHP_URL_HOST)],
            'payload' => [
                'genericClasses' => [[
                    'id' => $classId,
                ]],
                'genericObjects' => [[
                    'id' => $objectId,
                    'classId' => $classId,
                    'state' => 'ACTIVE',
                    'cardTitle' => self::localized($pass->organizerName),
                    'header' => self::localized($pass->eventTitle),
                    'subheader' => self::localized($pass->ticketTitle),
                    'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => $pass->barcodeValue,
                        'alternateText' => $pass->barcodeValue,
                    ],
                    'hexBackgroundColor' => '#6B46C1',
                    'textModulesData' => array_values(array_filter([
                        ['id' => 'attendee', 'header' => __('Attendee'), 'body' => $pass->attendeeName],
                        $pass->startDate ? ['id' => 'date', 'header' => __('Date'), 'body' => $pass->startDate] : null,
                    ])),
                    'linksModuleData' => [
                        'uris' => [[
                            'id' => 'ticket',
                            'uri' => $pass->ticketUrl,
                            'description' => __('View Ticket'),
                        ]],
                    ],
                    ...($pass->startDate ? ['validTimeInterval' => [
                        'start' => ['date' => $pass->startDate],
                        ...($pass->endDate ? ['end' => ['date' => $pass->endDate]] : []),
                    ]] : []),
                ]],
            ],
        ];

        try {
            return 'https://pay.google.com/gp/v/save/'.JWT::encode($claims, $serviceAccount['private_key'], 'RS256');
        } catch (Throwable $exception) {
            throw new WalletPassNotAvailableException(__('Unable to sign the Google Wallet pass.'), previous: $exception);
        }
    }

    private static function localized(string $value): array
    {
        return ['defaultValue' => ['language' => 'en-US', 'value' => $value]];
    }

    private static function identifier(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]/', '_', $value), '_') ?: 'event';
    }
}
