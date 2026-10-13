<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Email\Delivery;

use HiEvents\DomainObjects\Generated\EmailDeliveryDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Throwable;

class EmailDeliveryRecorder
{
    private const SES_MESSAGE_ID_HEADER = 'X-Message-ID';

    public function __construct(
        private readonly EmailDeliveryRepositoryInterface $emailDeliveryRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(SentMessage $sentMessage, ?string $mailableClass): void
    {
        try {
            $this->emailDeliveryRepository->create([
                EmailDeliveryDomainObjectAbstract::CATEGORY => $this->category($mailableClass),
                EmailDeliveryDomainObjectAbstract::PROVIDER_MESSAGE_ID => $this->providerMessageId($sentMessage),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to record an outgoing email delivery', [
                'mailable' => $mailableClass,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function category(?string $mailableClass): ?string
    {
        if ($mailableClass === null) {
            return null;
        }

        return Str::snake((string) preg_replace('/(Mail|Email)$/', '', class_basename($mailableClass)));
    }

    private function providerMessageId(SentMessage $sentMessage): string
    {
        $originalMessage = $sentMessage->getOriginalMessage();

        if ($originalMessage instanceof Message) {
            $sesMessageId = $originalMessage->getHeaders()->get(self::SES_MESSAGE_ID_HEADER)?->getBodyAsString();

            if ($sesMessageId !== null && $sesMessageId !== '') {
                return $sesMessageId;
            }
        }

        return $sentMessage->getMessageId();
    }
}
