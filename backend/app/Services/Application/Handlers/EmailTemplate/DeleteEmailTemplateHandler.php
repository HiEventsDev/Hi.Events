<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate;

use HiEvents\Exceptions\EmailTemplateNotFoundException;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\DeleteEmailTemplateDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;

class DeleteEmailTemplateHandler
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly EventSpamCheckDispatchService $eventSpamCheckDispatchService,
    ) {}

    /**
     * @throws EmailTemplateNotFoundException
     */
    public function handle(DeleteEmailTemplateDTO $dto): bool
    {
        $template = $this->emailTemplateRepository->findFirstWhere([
            'id' => $dto->id,
            'account_id' => $dto->account_id,
            'event_id' => $dto->event_id,
            'organizer_id' => $dto->organizer_id,
        ]);

        if (! $template) {
            throw new EmailTemplateNotFoundException(__('Email template not found'));
        }

        $deleted = $this->emailTemplateRepository->deleteById($template->getId());

        $this->eventSpamCheckDispatchService->dispatchForEmailTemplate($template);

        return $deleted;
    }
}
