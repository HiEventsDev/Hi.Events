<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class TwilioSmsProvider implements SmsProviderInterface
{
    private const API_URL = 'https://api.twilio.com/2010-04-01';

    private const MESSAGING_URL = 'https://messaging.twilio.com/v1';

    private const TIMEOUT_SECONDS = 15;

    private const RECIPIENT_OPTED_OUT = '21610';

    private const ACCOUNT_OR_SENDER_REFUSED = ['20003', '20005', '20403', '21701'];

    private const SENDERS_PAGE_SIZE = 1000;

    private const MAX_SENDER_PAGES = 10;

    private const RETRYABLE_STATUSES = [429, 503];

    public function __construct(
        private readonly HttpClient $http,
        private readonly TwilioRequestSignature $requestSignature,
    ) {}

    public function listSenders(SmsCredentialsDTO $credentials): array
    {
        $senders = [];
        $url = self::MESSAGING_URL.'/Services?PageSize='.self::SENDERS_PAGE_SIZE;

        for ($page = 0; $page < self::MAX_SENDER_PAGES && $url !== null; $page++) {
            $response = $this->successful($this->request(fn () => $this->client($credentials)->get($url)));

            foreach ($response->json('services') ?? [] as $service) {
                $senders[] = $this->sender($service);
            }

            $nextUrl = $response->json('meta.next_page_url');
            $url = is_string($nextUrl) && str_starts_with($nextUrl, self::MESSAGING_URL.'/') ? $nextUrl : null;
        }

        return $senders;
    }

    public function findSender(SmsCredentialsDTO $credentials): ?SmsSenderDTO
    {
        $response = $this->request(fn () => $this->client($credentials)->get($this->serviceUrl($credentials)));

        return $response->status() === 404 ? null : $this->sender($this->successful($response)->json());
    }

    public function configureInbound(SmsCredentialsDTO $credentials, string $inboundUrl): void
    {
        $this->successful($this->request(fn () => $this->client($credentials)->asForm()->post(
            $this->serviceUrl($credentials),
            ['InboundRequestUrl' => $inboundUrl, 'InboundMethod' => 'POST', 'UseInboundWebhookOnNumber' => 'false'],
        )));
    }

    public function send(SmsCredentialsDTO $credentials, string $to, string $body, ?string $statusCallbackUrl): string
    {
        $response = $this->successful($this->request(fn () => $this->client($credentials)->asForm()->post(
            self::API_URL.'/Accounts/'.rawurlencode($credentials->account_sid).'/Messages.json',
            array_filter([
                'To' => $to,
                'Body' => $body,
                'MessagingServiceSid' => $credentials->messaging_service_sid,
                'StatusCallback' => $statusCallbackUrl,
            ]),
        )));

        return (string) $response->json('sid');
    }

    public function isValidSignature(string $authToken, string $url, array $params, string $signature): bool
    {
        return $this->requestSignature->isValid($authToken, $url, $params, $signature);
    }

    private function client(SmsCredentialsDTO $credentials): PendingRequest
    {
        return $this->http->withBasicAuth($credentials->account_sid, $credentials->auth_token)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }

    private function serviceUrl(SmsCredentialsDTO $credentials): string
    {
        return self::MESSAGING_URL.'/Services/'.rawurlencode((string) $credentials->messaging_service_sid);
    }

    /**
     * @param  array<string, mixed>  $service
     */
    private function sender(array $service): SmsSenderDTO
    {
        return new SmsSenderDTO(sid: (string) $service['sid'], name: (string) ($service['friendly_name'] ?? $service['sid']));
    }

    /**
     * @param  callable(): Response  $request
     *
     * @throws SmsProviderException
     */
    private function request(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new SmsProviderException(
                message: __('We couldn\'t reach Twilio. Please try again.'),
                mayHaveBeenAccepted: ! $this->failedBeforeAnythingWasSent($exception),
            );
        }
    }

    private function failedBeforeAnythingWasSent(ConnectionException $exception): bool
    {
        $transportError = $exception->getPrevious();

        return ($transportError instanceof ConnectException || $transportError instanceof RequestException)
            && ($transportError->getHandlerContext()['request_size'] ?? null) === 0;
    }

    /**
     * @throws SmsProviderException
     */
    private function successful(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $code = (string) $response->json('code');
        $isUnauthorized = $response->status() === 401;
        $isRetryable = in_array($response->status(), self::RETRYABLE_STATUSES, true);

        throw new SmsProviderException(
            message: $isUnauthorized
                ? __('Twilio didn\'t accept those credentials. Check the Account SID and Auth Token.')
                : (string) ($response->json('message') ?? __('Twilio rejected the request.')),
            isPermanent: $response->clientError() && ! $isRetryable,
            recipientOptedOut: $code === self::RECIPIENT_OPTED_OUT,
            mayHaveBeenAccepted: $response->serverError() && ! $isRetryable,
            connectionRefused: $isUnauthorized || in_array($code, self::ACCOUNT_OR_SENDER_REFUSED, true),
        );
    }
}
