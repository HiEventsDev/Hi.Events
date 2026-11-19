<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class FakeSmsProvider implements SmsProviderInterface
{
    public const NUMBER_THAT_GETS_THE_ACCOUNT_REFUSED = '+12015550190';

    public const NUMBER_TWILIO_REJECTS = '+12015550191';

    public const NUMBER_OPTED_OUT_AT_TWILIO = '+12015550192';

    private const OUTBOX_TTL_SECONDS = 3600;

    private const OUTBOX_LOCK_SECONDS = 5;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TwilioRequestSignature $requestSignature,
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
    ) {}

    public function listSenders(SmsCredentialsDTO $credentials): array
    {
        return [$this->senderOf($credentials)];
    }

    public function findSender(SmsCredentialsDTO $credentials): ?SmsSenderDTO
    {
        $sender = $this->senderOf($credentials);

        return $sender->sid === $credentials->messaging_service_sid ? $sender : null;
    }

    public function configureInbound(SmsCredentialsDTO $credentials, string $inboundUrl): void {}

    /**
     * @throws SmsProviderException
     */
    public function send(SmsCredentialsDTO $credentials, string $to, string $body, ?string $statusCallbackUrl): string
    {
        $refusal = match ($to) {
            self::NUMBER_THAT_GETS_THE_ACCOUNT_REFUSED => new SmsProviderException('Authenticate', isPermanent: true, connectionRefused: true),
            self::NUMBER_TWILIO_REJECTS => new SmsProviderException("The 'To' number is not a valid mobile number.", isPermanent: true),
            self::NUMBER_OPTED_OUT_AT_TWILIO => new SmsProviderException('Attempt to send to unsubscribed recipient', isPermanent: true, recipientOptedOut: true),
            default => null,
        };

        if ($refusal !== null) {
            throw $refusal;
        }

        $messageId = 'SMfake'.Str::lower(Str::random(26));

        $this->logger->info('Fake SMS sent', ['to' => $to, 'body' => $body, 'message_id' => $messageId]);

        $key = $this->outboxKey($credentials->account_sid, $to);
        $this->cache->lock($key.':lock', self::OUTBOX_LOCK_SECONDS)->block(
            self::OUTBOX_LOCK_SECONDS,
            fn () => $this->cache->put($key, [...$this->cache->get($key, []), ['message_id' => $messageId, 'body' => $body]], self::OUTBOX_TTL_SECONDS),
        );

        return $messageId;
    }

    /**
     * @return array<int, array{message_id: string, body: string}>
     *
     * @throws SmsProviderException
     */
    public function sentTo(string $accountSid, string $to): array
    {
        if ($this->config->get('services.sms.driver') !== 'fake') {
            throw new SmsProviderException('The fake SMS provider is not the active driver.', isPermanent: true);
        }

        return $this->cache->get($this->outboxKey($accountSid, $to), []);
    }

    public function isValidSignature(string $authToken, string $url, array $params, string $signature): bool
    {
        return $this->requestSignature->isValid($authToken, $url, $params, $signature);
    }

    private function outboxKey(string $accountSid, string $to): string
    {
        return 'crm:fake-sms-outbox:'.$accountSid.':'.$to;
    }

    private function senderOf(SmsCredentialsDTO $credentials): SmsSenderDTO
    {
        return new SmsSenderDTO(sid: 'MG'.md5($credentials->account_sid), name: 'Test sender');
    }
}
