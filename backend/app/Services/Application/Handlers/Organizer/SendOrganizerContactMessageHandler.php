<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Mail\Organizer\OrganizerContactEmail;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\SendOrganizerContactMessageDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Mail\Mailer;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class SendOrganizerContactMessageHandler
{
    public function __construct(
        private readonly Mailer                       $mailer,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly HtmlPurifierService          $purifier,
    )
    {
    }

    public function handle(SendOrganizerContactMessageDTO $dto): void
    {
        $organizer = $this->organizerRepository->findById($dto->organizer_id);

        // Don't allow people to contact organizers that are not live, except for the organizer's own account
        if ($organizer->getStatus() !== OrganizerStatus::LIVE->value && $dto->account_id !== $organizer->getAccountId()) {
            throw new ResourceNotFoundException(__('Organizer not found'));
        }

        $purifiedMessage = $this->purifier->purify($dto->message);

        $this->mailer
            ->to($organizer->getEmail(), $organizer->getName())
            ->send(new OrganizerContactEmail(
                organizer: $organizer,
                senderName: $dto->name,
                senderEmail: $dto->email,
                messageContent: $purifiedMessage,
            ));
    }
}
