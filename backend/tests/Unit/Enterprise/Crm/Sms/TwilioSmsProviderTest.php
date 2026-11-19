<?php

namespace Tests\Unit\Enterprise\Crm\Sms;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCredentialsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\TwilioRequestSignature;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\TwilioSmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TwilioSmsProviderTest extends TestCase
{
    private const CREDENTIALS = ['account_sid' => 'AC00000000000000000000000000000001', 'auth_token' => 'secret', 'messaging_service_sid' => 'MG00000000000000000000000000000001'];

    public function test_a_message_is_sent_through_the_messaging_service(): void
    {
        $http = new Factory;
        $http->fake(['api.twilio.com/*' => $http->response(['sid' => 'SM123'], 201)]);

        $id = (new TwilioSmsProvider($http, new TwilioRequestSignature))->send(new SmsCredentialsDTO(...self::CREDENTIALS), '+353851234567', 'Hello', 'https://example.test/status');

        $this->assertSame('SM123', $id);
        $http->assertSent(static fn (Request $request) => $request['To'] === '+353851234567'
            && $request['MessagingServiceSid'] === self::CREDENTIALS['messaging_service_sid']
            && $request['StatusCallback'] === 'https://example.test/status');
    }

    public function test_a_number_that_replied_stop_is_a_permanent_opt_out(): void
    {
        $exception = $this->failureFor(400, ['code' => 21610, 'message' => 'Attempt to send to unsubscribed recipient']);

        $this->assertTrue($exception->isPermanent);
        $this->assertTrue($exception->recipientOptedOut);
    }

    public function test_rate_limits_and_outages_can_be_retried(): void
    {
        foreach ([$this->failureFor(429, ['code' => 20429, 'message' => 'Too many requests']), $this->failureFor(503, ['message' => 'Unavailable'])] as $exception) {
            $this->assertFalse($exception->isPermanent);
            $this->assertFalse($exception->mayHaveBeenAccepted);
        }
    }

    #[DataProvider('rejections')]
    public function test_a_request_twilio_rejects_is_never_retried(int $status, int $code): void
    {
        $exception = $this->failureFor($status, ['code' => $code, 'message' => 'Rejected']);

        $this->assertTrue($exception->isPermanent);
        $this->assertFalse($exception->mayHaveBeenAccepted);
        $this->assertFalse($exception->recipientOptedOut);
        $this->assertFalse($exception->connectionRefused);
    }

    public static function rejections(): array
    {
        return [
            'bad request: not a valid number' => [400, 21211],
            'forbidden: region not enabled' => [403, 21408],
            'not found' => [404, 20404],
            'unprocessable' => [422, 21617],
        ];
    }

    #[DataProvider('accountLevelRefusals')]
    public function test_twilio_refusing_the_account_or_sender_is_not_a_refusal_of_the_number(int $status, int $code): void
    {
        $exception = $this->failureFor($status, ['code' => $code, 'message' => 'Refused']);

        $this->assertTrue($exception->connectionRefused);
        $this->assertTrue($exception->isPermanent);
        $this->assertFalse($exception->mayHaveBeenAccepted);
    }

    public static function accountLevelRefusals(): array
    {
        return [
            'credentials no longer accepted' => [401, 20003],
            'unauthorized without a known code' => [401, 0],
            'account not active' => [403, 20005],
            'account suspended or closed' => [403, 20403],
            'messaging service no longer in the account' => [404, 21701],
        ];
    }

    public function test_a_refusal_of_one_number_is_not_a_refusal_of_the_connection(): void
    {
        $this->assertFalse($this->failureFor(400, ['code' => 21211, 'message' => 'Invalid To'])->connectionRefused);
        $this->assertFalse($this->failureFor(403, ['code' => 21408, 'message' => 'Region not enabled'])->connectionRefused);
    }

    #[DataProvider('ambiguousServerErrors')]
    public function test_a_server_error_after_the_request_arrived_may_have_been_accepted(int $status): void
    {
        $exception = $this->failureFor($status, ['message' => 'Something went wrong']);

        $this->assertTrue($exception->mayHaveBeenAccepted);
        $this->assertFalse($exception->isPermanent);
    }

    public static function ambiguousServerErrors(): array
    {
        return ['internal error' => [500], 'bad gateway' => [502], 'gateway timeout' => [504]];
    }

    #[DataProvider('failuresBeforeSending')]
    public function test_a_failure_before_any_of_the_request_left_can_be_retried(int $errno, string $message): void
    {
        $exception = $this->connectionFailure($message, ['errno' => $errno, 'request_size' => 0]);

        $this->assertFalse($exception->mayHaveBeenAccepted);
        $this->assertFalse($exception->isPermanent);
    }

    public static function failuresBeforeSending(): array
    {
        return [
            'the host could not be resolved' => [6, 'cURL error 6: Could not resolve host: api.twilio.com'],
            'the connection was refused' => [7, 'cURL error 7: Failed to connect to api.twilio.com port 443: Connection refused'],
            'connecting timed out' => [28, 'cURL error 28: Connection timed out after 10001 milliseconds'],
        ];
    }

    #[DataProvider('failuresAfterSending')]
    public function test_a_failure_after_the_request_left_may_have_been_accepted(?array $handlerContext, string $message): void
    {
        $exception = $this->connectionFailure($message, $handlerContext);

        $this->assertTrue($exception->mayHaveBeenAccepted);
        $this->assertFalse($exception->isPermanent);
    }

    public static function failuresAfterSending(): array
    {
        return [
            'no answer in time' => [['errno' => 28, 'request_size' => 412], 'cURL error 28: Operation timed out after 15001 milliseconds with 0 bytes received'],
            'an empty reply' => [['errno' => 52, 'request_size' => 412], 'cURL error 52: Empty reply from server'],
            'the connection was reset while waiting' => [['errno' => 56, 'request_size' => 412], 'cURL error 56: Recv failure: Connection reset by peer'],
            'a transport that does not say how far it got' => [[], 'Connection refused'],
            'a failure with no transport error attached' => [null, 'Connection timed out'],
        ];
    }

    public function test_the_chosen_messaging_service_is_looked_up_directly(): void
    {
        $http = new Factory;
        $http->fake(['messaging.twilio.com/v1/Services/'.self::CREDENTIALS['messaging_service_sid'] => $http->response(['sid' => self::CREDENTIALS['messaging_service_sid'], 'friendly_name' => 'Box office'])]);

        $sender = (new TwilioSmsProvider($http, new TwilioRequestSignature))->findSender(new SmsCredentialsDTO(...self::CREDENTIALS));

        $this->assertSame(self::CREDENTIALS['messaging_service_sid'], $sender->sid);
        $this->assertSame('Box office', $sender->name);
    }

    public function test_a_messaging_service_the_account_does_not_have_is_not_found(): void
    {
        $http = new Factory;
        $http->fake(['messaging.twilio.com/*' => $http->response(['code' => 20404, 'message' => 'Not found'], 404)]);

        $this->assertNull((new TwilioSmsProvider($http, new TwilioRequestSignature))->findSender(new SmsCredentialsDTO(...self::CREDENTIALS)));
    }

    public function test_every_page_of_messaging_services_is_listed(): void
    {
        $http = new Factory;
        $http->fake(static fn (Request $request) => str_contains($request->url(), 'Page=1')
            ? $http->response(['services' => [['sid' => 'MG2', 'friendly_name' => 'Second']], 'meta' => ['next_page_url' => null]])
            : $http->response(['services' => [['sid' => 'MG1', 'friendly_name' => 'First']], 'meta' => ['next_page_url' => 'https://messaging.twilio.com/v1/Services?PageSize=1000&Page=1&PageToken=abc']]));

        $senders = (new TwilioSmsProvider($http, new TwilioRequestSignature))->listSenders(new SmsCredentialsDTO(...self::CREDENTIALS));

        $this->assertSame(['MG1', 'MG2'], array_map(static fn ($sender) => $sender->sid, $senders));
    }

    public function test_a_next_page_outside_twilio_is_never_followed(): void
    {
        $http = new Factory;
        $http->fake(['*' => $http->response(['services' => [['sid' => 'MG1']], 'meta' => ['next_page_url' => 'https://evil.example/v1/Services?Page=1']])]);

        (new TwilioSmsProvider($http, new TwilioRequestSignature))->listSenders(new SmsCredentialsDTO(...self::CREDENTIALS));

        $http->assertSentCount(1);
    }

    #[DataProvider('signedRequests')]
    public function test_signatures_are_checked_over_the_url_and_sorted_parameters(string $token, string $url, array $params, ?string $signature, bool $isValid): void
    {
        $signature ??= base64_encode(hash_hmac('sha1', 'https://example.test/hookMessageSidSM1MessageStatusdelivered', 'secret', true));

        $this->assertSame($isValid, (new TwilioSmsProvider(new Factory, new TwilioRequestSignature))->isValidSignature($token, $url, $params, $signature));
    }

    public static function signedRequests(): array
    {
        $url = 'https://example.test/hook';

        return [
            'as signed' => ['secret', $url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], null, true],
            'parameters in another order' => ['secret', $url, ['MessageStatus' => 'delivered', 'MessageSid' => 'SM1'], null, true],
            'another auth token' => ['other', $url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], null, false],
            'a changed value' => ['secret', $url, ['MessageSid' => 'SM1', 'MessageStatus' => 'failed'], null, false],
            'an added parameter' => ['secret', $url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered', 'To' => '+353851234567'], null, false],
            'another url' => ['secret', $url.'/inbound', ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], null, false],
            'an empty signature' => ['secret', $url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], '', false],
        ];
    }

    private function connectionFailure(string $message, ?array $handlerContext): SmsProviderException
    {
        $http = new Factory;
        $transportError = $handlerContext === null ? null : new ConnectException($message, new GuzzleRequest('POST', 'https://api.twilio.com'), null, $handlerContext);
        $http->fake(static fn () => throw new ConnectionException($message, 0, $transportError));

        try {
            (new TwilioSmsProvider($http, new TwilioRequestSignature))->send(new SmsCredentialsDTO(...self::CREDENTIALS), '+353851234567', 'Hello', null);
        } catch (SmsProviderException $exception) {
            return $exception;
        }

        $this->fail('Expected the send to fail.');
    }

    private function failureFor(int $status, array $body): SmsProviderException
    {
        $http = new Factory;
        $http->fake(['api.twilio.com/*' => $http->response($body, $status)]);

        try {
            (new TwilioSmsProvider($http, new TwilioRequestSignature))->send(new SmsCredentialsDTO(...self::CREDENTIALS), '+353851234567', 'Hello', null);
        } catch (SmsProviderException $exception) {
            return $exception;
        }

        $this->fail('Expected the send to fail.');
    }
}
