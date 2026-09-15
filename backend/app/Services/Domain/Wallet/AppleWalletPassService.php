<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Wallet;

use HiEvents\DataTransferObjects\Wallet\TicketWalletPassData;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use PKPass\PKPass;
use Throwable;

class AppleWalletPassService
{
    public function create(TicketWalletPassData $pass): string
    {
        $config = config('services.wallet_passes.apple');
        $certificate = base64_decode((string) $config['certificate'], true);
        $wwdrCertificate = base64_decode((string) $config['wwdr_certificate'], true);

        if (! $certificate || ! $wwdrCertificate || ! $config['pass_type_identifier'] || ! $config['team_identifier']) {
            throw new WalletPassNotAvailableException(__('Apple Wallet is not configured.'));
        }

        $wwdrPath = tempnam(sys_get_temp_dir(), 'hievents-wwdr-');
        file_put_contents($wwdrPath, $wwdrCertificate);

        try {
            $builder = new PKPass;
            $builder->setCertificateString($certificate);
            $builder->setCertificatePassword((string) $config['certificate_password']);
            $builder->setWwdrCertificatePath($wwdrPath);
            $builder->setData($this->passDefinition($pass, $config));
            $builder->addFile(resource_path('wallet/icon.png'));
            $builder->addFile(resource_path('wallet/icon@2x.png'));

            return $builder->create();
        } catch (Throwable $exception) {
            throw new WalletPassNotAvailableException(__('Unable to create the Apple Wallet pass.'), previous: $exception);
        } finally {
            unlink($wwdrPath);
        }
    }

    private function passDefinition(TicketWalletPassData $pass, array $config): array
    {
        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $config['pass_type_identifier'],
            'serialNumber' => $pass->serialNumber,
            'teamIdentifier' => $config['team_identifier'],
            'organizationName' => $pass->organizerName,
            'description' => $pass->eventTitle,
            'logoText' => $pass->eventTitle,
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => 'rgb(107, 70, 193)',
            'labelColor' => 'rgb(237, 233, 254)',
            'barcode' => $this->barcode($pass->barcodeValue),
            'barcodes' => [$this->barcode($pass->barcodeValue)],
            ...($pass->startDate ? ['relevantDate' => $pass->startDate] : []),
            'eventTicket' => [
                'primaryFields' => [[
                    'key' => 'event',
                    'label' => __('Event'),
                    'value' => $pass->eventTitle,
                ]],
                'secondaryFields' => [[
                    'key' => 'attendee',
                    'label' => __('Attendee'),
                    'value' => $pass->attendeeName,
                ]],
                'auxiliaryFields' => array_values(array_filter([
                    ['key' => 'ticket', 'label' => __('Ticket'), 'value' => $pass->ticketTitle],
                    $pass->startDate ? ['key' => 'date', 'label' => __('Date'), 'value' => $pass->startDate, 'dateStyle' => 'PKDateStyleMedium', 'timeStyle' => 'PKDateStyleShort'] : null,
                ])),
                'backFields' => [[
                    'key' => 'ticketLink',
                    'label' => __('View Ticket'),
                    'value' => $pass->ticketUrl,
                ]],
            ],
        ];
    }

    private function barcode(string $value): array
    {
        return [
            'format' => 'PKBarcodeFormatQR',
            'message' => $value,
            'messageEncoding' => 'iso-8859-1',
            'altText' => $value,
        ];
    }
}
