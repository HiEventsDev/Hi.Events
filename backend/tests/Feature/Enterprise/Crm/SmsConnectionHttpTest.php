<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCredentialsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsProviderInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsSenderDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsConnectionHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private const ACCOUNT_SID = 'AC11111111111111111111111111111111';

    private const OTHER_ACCOUNT_SID = 'AC22222222222222222222222222222222';

    private const SERVICE_SID = 'MG00000000000000000000000000000001';

    private const OTHER_SERVICE_SID = 'MG00000000000000000000000000000002';

    private const AUTH_TOKEN = 'first-auth-token';

    private MockInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, true);

        $this->provider = Mockery::mock(SmsProviderInterface::class);
        $this->app->instance(SmsProviderInterface::class, $this->provider);
    }

    public function test_an_organizer_without_a_connection_gets_an_empty_answer(): void
    {
        $this->crm('GET', 'sms-connection', [], $this->ownerHeaders())->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_saving_checks_the_service_exists_points_replies_here_and_never_returns_the_token(): void
    {
        $this->provider->shouldReceive('findSender')
            ->once()
            ->withArgs(static fn (SmsCredentialsDTO $credentials) => $credentials->account_sid === self::ACCOUNT_SID && $credentials->auth_token === self::AUTH_TOKEN && $credentials->messaging_service_sid === self::SERVICE_SID)
            ->andReturn(new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'));
        $this->provider->shouldNotReceive('listSenders');
        $configuredUrl = null;
        $this->provider->shouldReceive('configureInbound')
            ->once()
            ->withArgs(static function (SmsCredentialsDTO $credentials, string $inboundUrl) use (&$configuredUrl): bool {
                $configuredUrl = $inboundUrl;

                return $credentials->messaging_service_sid === self::SERVICE_SID;
            });

        $saved = $this->crm('PUT', 'sms-connection', $this->connectionBody(['enabled_countries' => ['IE', 'US', 'IE']]), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.account_sid', self::ACCOUNT_SID)
            ->assertJsonPath('data.messaging_service_sid', self::SERVICE_SID)
            ->assertJsonPath('data.enabled_countries', ['IE', 'US']);

        $this->assertStringNotContainsString(self::AUTH_TOKEN, $saved->getContent());
        $row = DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole();
        $this->assertSame($this->accountId, (int) $row->account_id);
        $this->assertStringNotContainsString(self::AUTH_TOKEN, (string) $row->auth_token);
        $this->assertSame(app(SmsConnectionService::class)->inboundUrl($row->public_id), $configuredUrl);
        $this->assertSame(self::AUTH_TOKEN, app(SmsConnectionService::class)->findForOrganizer($this->organizerId)->getAuthToken());

        $read = $this->crm('GET', 'sms-connection', [], $this->ownerHeaders())->assertOk();
        $this->assertSame($saved->json('data'), $read->json('data'));
        $this->assertStringNotContainsString(self::AUTH_TOKEN, $read->getContent());
    }

    public function test_saving_again_without_a_token_reuses_the_stored_one_and_keeps_the_reply_address(): void
    {
        $publicId = $this->connect();
        $this->provider->shouldReceive('findSender')
            ->once()
            ->withArgs(static fn (SmsCredentialsDTO $credentials) => $credentials->auth_token === self::AUTH_TOKEN)
            ->andReturn(new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'));
        $this->provider->shouldReceive('configureInbound')
            ->once()
            ->withArgs(static fn (SmsCredentialsDTO $credentials, string $inboundUrl) => str_contains($inboundUrl, $publicId));

        $this->crm('PUT', 'sms-connection', $this->connectionBody(['auth_token' => null, 'enabled_countries' => ['GB']]), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.enabled_countries', ['GB']);

        $row = DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole();
        $this->assertSame($publicId, $row->public_id);
        $this->assertSame(self::AUTH_TOKEN, app(SmsConnectionService::class)->findForOrganizer($this->organizerId)->getAuthToken());
    }

    public function test_the_stored_token_is_not_used_for_a_different_twilio_account(): void
    {
        $this->connect();
        $before = (array) DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole();
        $this->provider->shouldNotReceive('listSenders');
        $this->provider->shouldNotReceive('findSender');
        $this->provider->shouldNotReceive('configureInbound');

        $this->crm('PUT', 'sms-connection', $this->connectionBody(['account_sid' => self::OTHER_ACCOUNT_SID, 'auth_token' => null]), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['messaging_service_sid']);
        $this->crm('POST', 'sms-connection/senders', ['account_sid' => self::OTHER_ACCOUNT_SID], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auth_token']);

        $this->assertSame($before, (array) DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole());
    }

    public function test_a_messaging_service_that_is_not_in_the_twilio_account_is_refused_and_nothing_is_saved(): void
    {
        $this->provider->shouldReceive('findSender')->once()->andReturn(null);
        $this->provider->shouldNotReceive('configureInbound');

        $this->crm('PUT', 'sms-connection', $this->connectionBody(), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['messaging_service_sid']);

        $this->assertSame(0, DB::table('crm_sms_connections')->count());
    }

    public function test_a_messaging_service_another_organizer_uses_is_refused_so_replies_reach_one_organizer(): void
    {
        $this->connect($this->otherOrganizerId);
        $this->provider->shouldReceive('findSender')->once()->andReturn(new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'));
        $this->provider->shouldNotReceive('configureInbound');

        $this->crm('PUT', 'sms-connection', $this->connectionBody(), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.messaging_service_sid.0', 'This Messaging Service is already connected to another organizer. Create a separate Messaging Service in Twilio for each organizer.');

        $this->assertSame(0, DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_credentials_twilio_refuses_are_reported_and_nothing_is_saved(): void
    {
        $this->provider->shouldReceive('findSender')->once()->andThrow(new SmsProviderException('Twilio said no.', isPermanent: true));
        $this->provider->shouldReceive('listSenders')->once()->andThrow(new SmsProviderException('Twilio said no.', isPermanent: true));
        $this->provider->shouldNotReceive('configureInbound');

        $this->crm('PUT', 'sms-connection', $this->connectionBody(), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.messaging_service_sid.0', 'Twilio said no.');
        $this->crm('POST', 'sms-connection/senders', ['account_sid' => self::ACCOUNT_SID, 'auth_token' => self::AUTH_TOKEN], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.auth_token.0', 'Twilio said no.');

        $this->assertSame(0, DB::table('crm_sms_connections')->count());
    }

    #[DataProvider('invalidConnections')]
    public function test_an_invalid_connection_is_rejected_before_twilio_is_asked(array $overrides, string $field): void
    {
        $this->provider->shouldNotReceive('listSenders');

        $this->crm('PUT', 'sms-connection', $this->connectionBody($overrides), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, DB::table('crm_sms_connections')->count());
    }

    public static function invalidConnections(): array
    {
        return [
            'account sid of the wrong shape' => [['account_sid' => 'SK11111111111111111111111111111111'], 'account_sid'],
            'account sid too short' => [['account_sid' => 'AC123'], 'account_sid'],
            'messaging service of the wrong shape' => [['messaging_service_sid' => 'PN00000000000000000000000000000001'], 'messaging_service_sid'],
            'no countries' => [['enabled_countries' => []], 'enabled_countries'],
            'a country texts are not offered in' => [['enabled_countries' => ['IE', 'FR']], 'enabled_countries.1'],
        ];
    }

    public function test_the_senders_of_a_twilio_account_are_listed_for_choosing(): void
    {
        $this->provider->shouldReceive('listSenders')
            ->once()
            ->withArgs(static fn (SmsCredentialsDTO $credentials) => $credentials->account_sid === self::ACCOUNT_SID && $credentials->auth_token === self::AUTH_TOKEN)
            ->andReturn([new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'), new SmsSenderDTO(sid: 'MG00000000000000000000000000000002', name: 'Marketing')]);

        $this->crm('POST', 'sms-connection/senders', ['account_sid' => self::ACCOUNT_SID, 'auth_token' => self::AUTH_TOKEN], $this->ownerHeaders())
            ->assertOk()
            ->assertExactJson(['data' => [
                ['sid' => self::SERVICE_SID, 'name' => 'Box office'],
                ['sid' => 'MG00000000000000000000000000000002', 'name' => 'Marketing'],
            ]]);
    }

    public function test_a_test_message_goes_to_the_number_in_international_format_without_delivery_tracking(): void
    {
        $this->connect();
        $this->provider->shouldReceive('send')
            ->once()
            ->withArgs(static fn (SmsCredentialsDTO $credentials, string $to, string $body, ?string $statusCallbackUrl) => $credentials->auth_token === self::AUTH_TOKEN
                && $credentials->messaging_service_sid === self::SERVICE_SID
                && $to === '+353851234567'
                && $statusCallbackUrl === null)
            ->andReturn('SM1');

        $this->crm('POST', 'sms-connection/test', ['phone' => '+353 85 123 4567'], $this->ownerHeaders())->assertNoContent();
    }

    #[DataProvider('unsendableTestMessages')]
    public function test_a_test_message_that_cannot_be_sent_explains_why_and_sends_nothing(bool $isConnected, string $phone): void
    {
        if ($isConnected) {
            $this->connect();
        }
        $this->provider->shouldNotReceive('send');

        $this->crm('POST', 'sms-connection/test', ['phone' => $phone], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public static function unsendableTestMessages(): array
    {
        return [
            'no connection yet' => [false, '+353851234567'],
            'a country that is not switched on' => [true, '+14155552671'],
            'not a phone number' => [true, 'call me'],
            'a landline' => [true, '+35312345678'],
        ];
    }

    public function test_a_test_message_twilio_refuses_is_reported_against_the_number(): void
    {
        $this->connect();
        $this->provider->shouldReceive('send')->once()->andThrow(new SmsProviderException('That number can\'t receive texts.', isPermanent: true));

        $this->crm('POST', 'sms-connection/test', ['phone' => '+353851234567'], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'That number can\'t receive texts.');
    }

    public function test_disconnecting_removes_only_this_organizers_connection(): void
    {
        $this->connect();
        $otherPublicId = $this->connect($this->otherOrganizerId, self::OTHER_SERVICE_SID);

        $this->crm('DELETE', 'sms-connection', [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame([$otherPublicId], DB::table('crm_sms_connections')->pluck('public_id')->all());
        $this->crm('GET', 'sms-connection', [], $this->ownerHeaders())->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_reconnecting_keeps_the_reply_address_twilio_already_points_at(): void
    {
        $publicId = $this->connect();
        $otherPublicId = $this->connect($this->otherOrganizerId, self::OTHER_SERVICE_SID);
        $this->crm('DELETE', 'sms-connection', [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame($publicId, $this->connect());
        $this->assertNotSame($publicId, $otherPublicId);
        $this->assertMatchesRegularExpression('/^smsc_[a-z0-9]{24}$/', $publicId);
    }

    public function test_a_messaging_service_another_organizer_claims_while_saving_is_refused_without_an_error(): void
    {
        $this->provider->shouldReceive('findSender')->once()->andReturn(new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'));
        $this->provider->shouldReceive('configureInbound')->once()->andReturnUsing(fn () => $this->insertConnection($this->otherOrganizerId));

        $this->crm('PUT', 'sms-connection', $this->connectionBody(), $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.messaging_service_sid.0', 'This Messaging Service is already connected to another organizer. Create a separate Messaging Service in Twilio for each organizer.');

        $this->assertSame([$this->otherOrganizerId], DB::table('crm_sms_connections')->pluck('organizer_id')->all());
    }

    public function test_saving_the_same_connection_twice_at_once_keeps_one_connection(): void
    {
        $this->provider->shouldReceive('findSender')->once()->andReturn(new SmsSenderDTO(sid: self::SERVICE_SID, name: 'Box office'));
        $this->provider->shouldReceive('configureInbound')->once()->andReturnUsing(fn () => $this->insertConnection($this->organizerId));

        $this->crm('PUT', 'sms-connection', $this->connectionBody(), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.messaging_service_sid', self::SERVICE_SID);

        $this->assertSame(1, DB::table('crm_sms_connections')->count());
    }

    public function test_without_the_text_message_feature_a_connection_can_be_read_and_removed_but_not_set_up_or_used(): void
    {
        $this->connect();
        $before = (array) DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false);
        $this->provider->shouldNotReceive('listSenders');
        $this->provider->shouldNotReceive('findSender');
        $this->provider->shouldNotReceive('configureInbound');
        $this->provider->shouldNotReceive('send');

        $this->crm('PUT', 'sms-connection', $this->connectionBody(['enabled_countries' => ['GB']]), $this->ownerHeaders())->assertForbidden();
        $this->crm('POST', 'sms-connection/senders', ['account_sid' => self::ACCOUNT_SID, 'auth_token' => self::AUTH_TOKEN], $this->ownerHeaders())->assertForbidden();
        $this->crm('POST', 'sms-connection/test', ['phone' => '+353851234567'], $this->ownerHeaders())->assertForbidden();

        $this->assertSame($before, (array) DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->sole());
        $this->crm('GET', 'sms-connection', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.account_sid', self::ACCOUNT_SID);
        $this->crm('DELETE', 'sms-connection', [], $this->ownerHeaders())->assertNoContent();
        $this->assertSame(0, DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->count());
    }

    private function connect(?int $organizerId = null, string $serviceSid = self::SERVICE_SID): string
    {
        $this->provider->shouldReceive('findSender')->once()->andReturn(new SmsSenderDTO(sid: $serviceSid, name: 'Box office'));
        $this->provider->shouldReceive('configureInbound')->once();

        return app(SmsConnectionService::class)
            ->save($this->accountId, $organizerId ?? $this->organizerId, self::ACCOUNT_SID, self::AUTH_TOKEN, $serviceSid, ['IE'])
            ->getPublicId();
    }

    private function insertConnection(int $organizerId): void
    {
        DB::table('crm_sms_connections')->insert([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'public_id' => 'smsc_'.str_repeat('a', 24),
            'provider' => SmsConnectionService::PROVIDER_TWILIO,
            'account_sid' => self::ACCOUNT_SID,
            'auth_token' => encrypt(self::AUTH_TOKEN),
            'messaging_service_sid' => self::SERVICE_SID,
            'enabled_countries' => json_encode(['IE']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function connectionBody(array $overrides = []): array
    {
        return [
            'account_sid' => self::ACCOUNT_SID,
            'auth_token' => self::AUTH_TOKEN,
            'messaging_service_sid' => self::SERVICE_SID,
            'enabled_countries' => ['IE'],
            ...$overrides,
        ];
    }
}
