<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Email\Delivery;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;

class EmailProviderMessageIdResolver
{
    private const SES_MESSAGE_ID_HEADER = 'X-Message-ID';

    public function resolve(SentMessage $sentMessage): string
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
