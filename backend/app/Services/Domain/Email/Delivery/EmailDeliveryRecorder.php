<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Email\Delivery;

use HiEvents\DomainObjects\Generated\EmailDeliveryDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Throwable;

class EmailDeliveryRecorder
{
    public function __construct(
        private readonly EmailDeliveryRepositoryInterface $emailDeliveryRepository,
        private readonly EmailProviderMessageIdResolver $providerMessageIdResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(SentMessage $sentMessage, ?string $mailableClass): void
    {
        try {
            $this->emailDeliveryRepository->create([
                EmailDeliveryDomainObjectAbstract::CATEGORY => $this->category($mailableClass),
                EmailDeliveryDomainObjectAbstract::PROVIDER_MESSAGE_ID => $this->providerMessageIdResolver->resolve($sentMessage),
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
}
