<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use Aws\Command;
use Aws\Exception\AwsException;
use GuzzleHttp\Psr7\Response;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTransportErrorClassifier;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Exception\TransportException as HttpClientTransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\TestCase;

class CampaignTransportErrorClassifierTest extends TestCase
{
    private const AT = '[2026-11-18T10:00:00.000000+00:00] ';

    public static function transportErrors(): array
    {
        return [
            'smtp connection that never opened because the connect timed out' => [
                static fn () => new TransportException('Connection could not be established with host "smtp.example.test:587": stream_socket_client(): Unable to connect to smtp.example.test:587 (Connection timed out)'),
                null,
            ],
            'smtp connection that never opened, wrapped by a failover transport' => [
                static fn () => new TransportException('All transports failed.', 0, new TransportException('Connection could not be established with host "ssl://smtp.example.test:465": stream_socket_client(): Unable to connect to ssl://smtp.example.test:465 (Operation timed out)')),
                null,
            ],
            'smtp login refused before any message was named' => [
                static fn () => self::smtp(new TransportException('Failed to authenticate on SMTP server with username "mailer".', 535), ['EHLO app' => '250 OK', 'AUTH LOGIN' => '535 5.7.8 Bad credentials']),
                null,
            ],
            'smtp server stopped answering before the message body was sent' => [
                static fn () => self::smtp(new TransportException('Connection to "smtp.example.test:587" timed out.'), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => null]),
                null,
            ],
            'smtp server asked to try again later' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250/251/252" but got code "451", with message "451 4.7.1 Greylisted".', 451), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '451 4.7.1 Greylisted']),
                null,
            ],
            'smtp server closing the channel after the message body' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250" but got code "421", with message "421 4.3.2 Shutting down".', 421), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '250 OK', 'DATA' => '354 Go', '.' => '421 4.3.2 Shutting down']),
                null,
            ],
            'smtp server stopped answering after the message body was sent' => [
                static fn () => self::smtp(new TransportException('Connection to "smtp.example.test:587" timed out.'), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '250 OK', 'DATA' => '354 Go', '.' => null]),
                CrmRecipientFailureReason::TIMEOUT,
            ],
            'smtp connection reset after the message body was sent' => [
                static fn () => self::smtp(new TransportException('Connection to "smtp.example.test:587" has been closed unexpectedly.'), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '250 OK', 'DATA' => '354 Go', '.' => null]),
                CrmRecipientFailureReason::TIMEOUT,
            ],
            'smtp error with no record of what was sent' => [
                static fn () => new TransportException('Connection to "smtp.example.test:587" has been closed unexpectedly.'),
                CrmRecipientFailureReason::TIMEOUT,
            ],
            'smtp server refusing the sender' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.7.1 Sender rejected".', 550), ['MAIL FROM:<a@example.test>' => '550 5.7.1 Sender rejected']),
                CrmRecipientFailureReason::PROVIDER_REJECTED,
            ],
            'smtp server refusing a recipient for policy reasons' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.7.1 Relaying denied".', 550), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '550 5.7.1 Relaying denied']),
                CrmRecipientFailureReason::PROVIDER_REJECTED,
            ],
            'smtp server refusing a recipient without saying why' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 Rejected".', 550), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '550 Rejected']),
                CrmRecipientFailureReason::PROVIDER_REJECTED,
            ],
            'smtp server saying the mailbox does not exist' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".', 550), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '550 5.1.1 User unknown']),
                CrmRecipientFailureReason::PROVIDER_SUPPRESSED,
            ],
            'smtp server saying the mailbox is disabled' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.2.1 Mailbox disabled".', 550), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '550 5.2.1 Mailbox disabled']),
                CrmRecipientFailureReason::PROVIDER_SUPPRESSED,
            ],
            'smtp server rejecting the content while quoting a mailbox code' => [
                static fn () => self::smtp(new UnexpectedResponseException('Expected response code "250" but got code "554", with message "554 5.1.1 rejected".', 554), ['MAIL FROM:<a@example.test>' => '250 OK', 'RCPT TO:<b@example.test>' => '250 OK', 'DATA' => '354 Go', '.' => '554 5.1.1 rejected']),
                CrmRecipientFailureReason::PROVIDER_REJECTED,
            ],
            'api rate limit' => [static fn () => self::api(429), null],
            'api unavailable' => [static fn () => self::api(503), null],
            'api request timeout' => [static fn () => self::api(408), null],
            'api server error' => [static fn () => self::api(500), CrmRecipientFailureReason::TIMEOUT],
            'api bad gateway' => [static fn () => self::api(502), CrmRecipientFailureReason::TIMEOUT],
            'api rejection' => [static fn () => self::api(400), CrmRecipientFailureReason::PROVIDER_REJECTED],
            'postmark rejection that is not about the recipient' => [static fn () => self::api(422, '{"ErrorCode":300,"Message":"Invalid email request"}'), CrmRecipientFailureReason::PROVIDER_REJECTED],
            'postmark inactive recipient' => [static fn () => self::api(422, '{"ErrorCode":406,"Message":"inactive recipient"}'), CrmRecipientFailureReason::PROVIDER_SUPPRESSED],
            'api request that never left' => [static fn () => self::unreachableApi(['size_upload' => 0.0]), null],
            'api request that was sent but never answered' => [static fn () => self::unreachableApi(['size_upload' => 812.0]), CrmRecipientFailureReason::TIMEOUT],
            'api request with no record of what was sent' => [static fn () => self::unreachableApi([]), CrmRecipientFailureReason::TIMEOUT],
            'ses rejecting the message' => [static fn () => self::ses('MessageRejected', 400), CrmRecipientFailureReason::PROVIDER_REJECTED],
            'ses throttling' => [static fn () => self::ses('Throttling', 400), null],
            'ses server error' => [static fn () => self::ses('InternalFailure', 500), CrmRecipientFailureReason::TIMEOUT],
            'ses request that got no response' => [static fn () => self::ses(null, null), CrmRecipientFailureReason::TIMEOUT],
        ];
    }

    #[DataProvider('transportErrors')]
    public function test_only_an_error_the_provider_cannot_have_accepted_is_left_for_a_retry(callable $makeException, ?CrmRecipientFailureReason $expected): void
    {
        $this->assertSame($expected, (new CampaignTransportErrorClassifier)->permanentFailureReason($makeException()));
    }

    /**
     * @param  array<string, string|null>  $exchange
     */
    private static function smtp(TransportException $exception, array $exchange): TransportException
    {
        $transcript = self::AT."< 220 smtp.example.test ESMTP\r\n";

        foreach ($exchange as $command => $reply) {
            $transcript .= self::AT.'> '.$command."\n".($reply === null ? '' : self::AT.'< '.$reply."\r\n");
        }

        $exception->appendDebug($transcript);

        return $exception;
    }

    private static function api(int $status, string $body = '{}'): HttpTransportException
    {
        return new HttpTransportException(
            'Unable to send an email (code '.$status.').',
            (new MockHttpClient(new MockResponse($body, ['http_code' => $status])))->request('POST', 'https://api.mail.example.test/send'),
        );
    }

    private static function unreachableApi(array $transferInfo): HttpTransportException
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andThrow(new HttpClientTransportException('Idle timeout reached for "https://api.mail.example.test/send".'));
        $response->shouldReceive('getInfo')->andReturn($transferInfo);

        return new HttpTransportException('Could not reach the remote mail server.', $response);
    }

    private static function ses(?string $errorCode, ?int $status): TransportException
    {
        return new TransportException('Request to AWS SES API failed. Reason: failed.', 0, new AwsException(
            'failed',
            new Command('SendEmail'),
            array_filter(['code' => $errorCode, 'response' => $status === null ? null : new Response($status)]),
        ));
    }
}
