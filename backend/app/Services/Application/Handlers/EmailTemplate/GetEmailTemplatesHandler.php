<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate;

use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\GetEmailTemplatesDTO;
use Illuminate\Support\Collection;

class GetEmailTemplatesHandler
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository
    ) {
    }

    public function handle(GetEmailTemplatesDTO $dto): Collection
    {
        $conditions = [
            'account_id' => $dto->account_id,
        ];

        if ($dto->event_id) {
            $conditions['event_id'] = $dto->event_id;
        }

        if ($dto->organizer_id) {
            $conditions['organizer_id'] = $dto->organizer_id;
        }

        if ($dto->template_type) {
            $conditions['template_type'] = $dto->template_type->value;
        }

        if (!$dto->include_inactive) {
            $conditions['is_active'] = true;
        }

        return $this->emailTemplateRepository->findWhere($conditions);
    }
}