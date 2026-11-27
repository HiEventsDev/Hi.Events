<?php

namespace HiEvents\Services\Application\Handlers\Message;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\MessagePreviewDTO;
use HiEvents\Services\Application\Handlers\Message\DTO\PreviewMessageDTO;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Contracts\Config\Repository;

class PreviewMessageHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly HtmlPurifierService $purifier,
        private readonly MailBrandingService $brandingService,
        private readonly Repository $config,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(PreviewMessageDTO $preview): MessagePreviewDTO
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer'
            ))
            ->findById($preview->event_id);

        $eventSettings = $event->getEventSettings();

        if ($eventSettings === null) {
            throw new ResourceNotFoundException(__('This event has no settings to preview a message with.'));
        }

        $subject = trim((string) $preview->subject);

        $mail = new EventMessage(
            event: $event,
            eventSettings: $eventSettings,
            messageData: new SendMessageDTO(
                account_id: $preview->account_id,
                event_id: $preview->event_id,
                subject: $subject,
                message: (string) $this->purifier->purify($preview->message),
                type: MessageTypeEnum::ALL_ATTENDEES,
                is_test: false,
                send_copy_to_current_user: false,
                sent_by_user_id: $preview->user_id,
            ),
        );

        return new MessagePreviewDTO(
            html: $mail->renderAsSent(),
            subject: $subject,
            from_name: (string) ($this->brandingService->forMail($mail)->senderName ?? $this->config->get('mail.from.name')),
            reply_to: $eventSettings->getSupportEmail(),
        );
    }
}
