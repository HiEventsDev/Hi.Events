<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Wallet;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use HiEvents\DataTransferObjects\Wallet\TicketWalletPassData;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use HiEvents\Services\Domain\Wallet\GoogleWalletPassService;
use Tests\TestCase;

class GoogleWalletPassServiceTest extends TestCase
{
    public function test_it_generates_a_signed_save_url_with_ticket_details(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];
        config(['services.wallet_passes.google' => [
            'issuer_id' => '3388000000012345678',
            'service_account' => base64_encode(json_encode([
                'client_email' => 'wallet@example.iam.gserviceaccount.com',
                'private_key' => $privateKey,
            ], JSON_THROW_ON_ERROR)),
        ]]);

        $url = app(GoogleWalletPassService::class)->createSaveUrl($this->passData());
        $jwt = substr($url, strlen('https://pay.google.com/gp/v/save/'));
        $claims = json_decode(json_encode(JWT::decode($jwt, new Key($publicKey, 'RS256')), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('wallet@example.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('3388000000012345678.event_42', $claims['payload']['genericClasses'][0]['id']);
        $this->assertSame('PUBLIC-123', $claims['payload']['genericObjects'][0]['barcode']['value']);
        $this->assertSame('Ada Lovelace', $claims['payload']['genericObjects'][0]['textModulesData'][0]['body']);
    }

    public function test_it_rejects_missing_configuration(): void
    {
        config(['services.wallet_passes.google' => [
            'issuer_id' => null,
            'service_account' => null,
        ]]);

        $this->expectException(WalletPassNotAvailableException::class);

        app(GoogleWalletPassService::class)->createSaveUrl($this->passData());
    }

    private function passData(): TicketWalletPassData
    {
        return new TicketWalletPassData(
            eventId: 42,
            serialNumber: 'PUBLIC-123',
            eventTitle: 'Laravel Live',
            organizerName: 'Hi.Events',
            ticketTitle: 'General admission',
            attendeeName: 'Ada Lovelace',
            barcodeValue: 'PUBLIC-123',
            startDate: '2026-09-20T18:00:00+02:00',
            endDate: '2026-09-20T20:00:00+02:00',
            timezone: 'Europe/Berlin',
            ticketUrl: 'https://tickets.example/product/42/abc',
        );
    }
}
