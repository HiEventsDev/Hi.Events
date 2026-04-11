<?php

namespace HiEvents\Services\Application\Handlers\Email\Ses;

use HiEvents\Exceptions\SnsSignatureVerificationException;
use HiEvents\Services\Application\Handlers\Email\Ses\DTO\SesWebhookDTO;
use HiEvents\Services\Domain\Email\Ses\EventHandlers\BounceHandler;
use HiEvents\Services\Domain\Email\Ses\EventHandlers\ComplaintHandler;
use HiEvents\Services\Infrastructure\Aws\SnsSignatureVerificationService;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class IncomingSesWebhookHandler
{
    public function __construct(
        private readonly BounceHandler                    $bounceHandler,
        private readonly ComplaintHandler                 $complaintHandler,
        private readonly SnsSignatureVerificationService  $signatureVerificationService,
        private readonly Logger                           $logger,
        private readonly Repository                       $cache,
    )
    {
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(SesWebhookDTO $webhookDTO): void
    {
        try {
            $payload = json_decode($webhookDTO->payload, true, 512, JSON_THROW_ON_ERROR);

            $this->signatureVerificationService->verify($payload);

            // Handle SNS SubscriptionConfirmation
            if (($payload['Type'] ?? null) === 'SubscriptionConfirmation') {
                $this->handleSubscriptionConfirmation($payload);
                return;
            }

            // Only process Notification type
            if (($payload['Type'] ?? null) !== 'Notification') {
                $this->logger->debug('Received non-Notification SNS message type', [
                    'type' => $payload['Type'] ?? 'unknown',
                ]);
                return;
            }

            // Validate TopicArn if configured
            $expectedTopicArn = config('services.ses.sns_topic_arn');
            if ($expectedTopicArn && ($payload['TopicArn'] ?? null) !== $expectedTopicArn) {
                $this->logger->warning('SNS TopicArn mismatch', [
                    'expected' => $expectedTopicArn,
                    'received' => $payload['TopicArn'] ?? 'none',
                ]);
                return;
            }

            // Deduplicate via SNS MessageId
            $snsMessageId = $payload['MessageId'] ?? null;
            if ($snsMessageId && $this->hasMessageBeenHandled($snsMessageId)) {
                $this->logger->debug('SNS message already handled', [
                    'message_id' => $snsMessageId,
                ]);
                return;
            }

            // Parse the inner SES message
            $message = json_decode($payload['Message'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

            // SES Notifications use 'notificationType', SES Event Destinations use 'eventType'
            $notificationType = $message['notificationType'] ?? $message['eventType'] ?? null;

            switch ($notificationType) {
                case 'Bounce':
                    $this->bounceHandler->handle($message, $payload);
                    break;
                case 'Complaint':
                    $this->complaintHandler->handle($message, $payload);
                    break;
                default:
                    $this->logger->debug('Unhandled SES notification type', [
                        'notification_type' => $notificationType,
                    ]);
                    break;
            }

            if ($snsMessageId) {
                $this->markMessageAsHandled($snsMessageId);
            }

        } catch (SnsSignatureVerificationException $exception) {
            $this->logger->warning('SNS signature verification failed', [
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        } catch (JsonException $exception) {
            $this->logger->error('Invalid JSON in SES webhook payload', [
                'payload' => $webhookDTO->payload,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled SES webhook error: ' . $exception->getMessage(), [
                'payload' => $webhookDTO->payload,
            ]);
            throw $exception;
        }
    }

    private function handleSubscriptionConfirmation(array $payload): void
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? null;

        if (!$subscribeUrl) {
            $this->logger->warning('SubscriptionConfirmation missing SubscribeURL');
            return;
        }

        if (!$this->isValidSnsSubscribeUrl($subscribeUrl)) {
            $this->logger->warning('SubscriptionConfirmation has invalid SubscribeURL', [
                'subscribe_url' => $subscribeUrl,
            ]);
            return;
        }

        $this->logger->info('Confirming SNS subscription', [
            'topic_arn' => $payload['TopicArn'] ?? 'unknown',
        ]);

        Http::get($subscribeUrl);
    }

    private function isValidSnsSubscribeUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['host'], $parsed['scheme'])) {
            return false;
        }

        if ($parsed['scheme'] !== 'https') {
            return false;
        }

        return (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/', strtolower($parsed['host']));
    }

    private function hasMessageBeenHandled(string $messageId): bool
    {
        return $this->cache->has('ses_sns_message_' . $messageId);
    }

    private function markMessageAsHandled(string $messageId): void
    {
        $this->cache->put('ses_sns_message_' . $messageId, true, now()->addMinutes(60));
    }
}
