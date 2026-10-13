<?php

declare(strict_types=1);

namespace HiEvents\Listeners\Mail;

use HiEvents\Services\Domain\Email\Delivery\EmailDeliveryRecorder;
use Illuminate\Mail\Events\MessageSent;

class RecordEmailDeliveryListener
{
    public function __construct(
        private readonly EmailDeliveryRecorder $recorder,
    ) {}

    public function handle(MessageSent $event): void
    {
        $this->recorder->record(
            $event->sent->getSymfonySentMessage(),
            $event->data['__laravel_mailable'] ?? null,
        );
    }
}
