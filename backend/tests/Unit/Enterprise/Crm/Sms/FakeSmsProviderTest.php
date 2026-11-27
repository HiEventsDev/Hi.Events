<?php

namespace Tests\Unit\Enterprise\Crm\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\FakeSmsProvider;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCredentialsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\TwilioRequestSignature;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Tests\TestCase;

class FakeSmsProviderTest extends TestCase
{
    private const ACCOUNT_SID = 'AC00000000000000000000000000000001';

    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new CacheRepository(new ArrayStore);
    }

    public function test_the_fake_provider_checks_signatures_the_same_way(): void
    {
        $fake = $this->fakeProvider();
        $params = ['From' => '+353851234567', 'Body' => 'STOP'];
        $signature = (new TwilioRequestSignature)->sign('e2e-token', 'https://example.test/hook', $params);

        $this->assertTrue($fake->isValidSignature('e2e-token', 'https://example.test/hook', $params, $signature));
        $this->assertFalse($fake->isValidSignature('other-token', 'https://example.test/hook', $params, $signature));
        $this->assertFalse($fake->isValidSignature('e2e-token', 'https://example.test/other', $params, $signature));
        $this->assertFalse($fake->isValidSignature('e2e-token', 'https://example.test/hook', ['Body' => 'START', 'From' => '+353851234567'], $signature));
    }

    public function test_the_fake_provider_only_knows_the_service_derived_from_the_account(): void
    {
        $fake = $this->fakeProvider();
        $sid = $fake->listSenders(new SmsCredentialsDTO('AC1', 'token'))[0]->sid;

        $this->assertSame($sid, $fake->findSender(new SmsCredentialsDTO('AC1', 'token', $sid))->sid);
        $this->assertNull($fake->findSender(new SmsCredentialsDTO('AC1', 'token', 'MGsomethingelse')));
    }

    public function test_every_text_sent_to_a_number_can_be_read_back_in_the_order_it_was_sent(): void
    {
        $fake = $this->fakeProvider();
        $credentials = new SmsCredentialsDTO(self::ACCOUNT_SID, 'token');

        $firstId = $fake->send($credentials, '+353851234567', "Acme: Doors at 7\nOpt out: https://example.test/u/1.abc", null);
        $secondId = $fake->send($credentials, '+353851234567', 'Acme: Second text', null);
        $fake->send($credentials, '+12015550123', "Acme: Doors at 7\nReply STOP to opt out.", null);

        $this->assertSame(
            [
                ['message_id' => $firstId, 'body' => "Acme: Doors at 7\nOpt out: https://example.test/u/1.abc"],
                ['message_id' => $secondId, 'body' => 'Acme: Second text'],
            ],
            $fake->sentTo(self::ACCOUNT_SID, '+353851234567'),
        );
        $this->assertSame(["Acme: Doors at 7\nReply STOP to opt out."], array_column($fake->sentTo(self::ACCOUNT_SID, '+12015550123'), 'body'));
    }

    public function test_texts_sent_through_another_account_or_to_another_number_are_not_read_back(): void
    {
        $fake = $this->fakeProvider();
        $fake->send(new SmsCredentialsDTO(self::ACCOUNT_SID, 'token'), '+353851234567', 'Acme: Doors at 7', null);

        $this->assertSame([], $fake->sentTo('AC00000000000000000000000000000002', '+353851234567'));
        $this->assertSame([], $fake->sentTo(self::ACCOUNT_SID, '+353857654321'));
    }

    #[DataProvider('numbersTheFakeRefuses')]
    public function test_a_test_number_is_refused_the_way_twilio_would_refuse_it_and_nothing_is_sent(string $number, bool $connectionRefused, bool $recipientOptedOut): void
    {
        $fake = $this->fakeProvider();

        try {
            $fake->send(new SmsCredentialsDTO(self::ACCOUNT_SID, 'token'), $number, 'Acme: Doors at 7', null);
            $this->fail('The text was sent.');
        } catch (SmsProviderException $exception) {
            $this->assertTrue($exception->isPermanent);
            $this->assertFalse($exception->mayHaveBeenAccepted);
            $this->assertSame($connectionRefused, $exception->connectionRefused);
            $this->assertSame($recipientOptedOut, $exception->recipientOptedOut);
        }

        $this->assertSame([], $fake->sentTo(self::ACCOUNT_SID, $number));
    }

    public static function numbersTheFakeRefuses(): array
    {
        return [
            'the account is refused' => [FakeSmsProvider::NUMBER_THAT_GETS_THE_ACCOUNT_REFUSED, true, false],
            'the number is rejected' => [FakeSmsProvider::NUMBER_TWILIO_REJECTS, false, false],
            'the number opted out at Twilio' => [FakeSmsProvider::NUMBER_OPTED_OUT_AT_TWILIO, false, true],
        ];
    }

    #[DataProvider('driversThatAreNotFake')]
    public function test_sent_texts_cannot_be_read_unless_the_fake_provider_is_the_driver(?string $driver): void
    {
        $this->fakeProvider()->send(new SmsCredentialsDTO(self::ACCOUNT_SID, 'token'), '+353851234567', 'Acme: Doors at 7', null);

        $this->expectException(SmsProviderException::class);

        $this->fakeProvider($driver)->sentTo(self::ACCOUNT_SID, '+353851234567');
    }

    public static function driversThatAreNotFake(): array
    {
        return [
            'twilio' => ['twilio'],
            'unset' => [null],
        ];
    }

    private function fakeProvider(?string $driver = 'fake'): FakeSmsProvider
    {
        return new FakeSmsProvider(
            new NullLogger,
            new TwilioRequestSignature,
            $this->cache,
            new ConfigRepository(['services' => ['sms' => ['driver' => $driver]]]),
        );
    }
}
