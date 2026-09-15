<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Wallet;

use HiEvents\DataTransferObjects\Wallet\TicketWalletPassData;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use HiEvents\Services\Domain\Wallet\AppleWalletPassService;
use Tests\TestCase;

class AppleWalletPassServiceTest extends TestCase
{
    public function test_it_rejects_missing_configuration(): void
    {
        config(['services.wallet_passes.apple' => [
            'pass_type_identifier' => null,
            'team_identifier' => null,
            'certificate' => null,
            'certificate_password' => null,
            'wwdr_certificate' => null,
        ]]);

        $this->expectException(WalletPassNotAvailableException::class);

        app(AppleWalletPassService::class)->create(new TicketWalletPassData(
            eventId: 42,
            serialNumber: 'PUBLIC-123',
            eventTitle: 'Laravel Live',
            organizerName: 'Hi.Events',
            ticketTitle: 'General admission',
            attendeeName: 'Ada Lovelace',
            barcodeValue: 'PUBLIC-123',
            startDate: null,
            endDate: null,
            timezone: 'UTC',
            ticketUrl: 'https://tickets.example/product/42/abc',
        ));
    }
}
