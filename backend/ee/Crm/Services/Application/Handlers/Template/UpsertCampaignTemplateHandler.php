<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Template;

use HiEvents\DomainObjects\CrmCampaignTemplateDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignTemplateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignTemplateRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\DTO\UpsertCampaignTemplateDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpsertCampaignTemplateHandler
{
    public const MAX_TEMPLATES_PER_ORGANIZER = 100;

    public function __construct(
        private readonly CrmCampaignTemplateRepositoryInterface $templateRepository,
        private readonly CampaignHtmlSanitizer $sanitizer,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(UpsertCampaignTemplateDTO $dto): CrmCampaignTemplateDomainObject
    {
        $name = trim($dto->name);

        if ($dto->template_id !== null && $this->templateRepository->findFirstWhere([
            CrmCampaignTemplateDomainObjectAbstract::ID => $dto->template_id,
            CrmCampaignTemplateDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]) === null) {
            throw new ResourceNotFoundException(__('Template not found'));
        }

        if ($this->templateRepository->nameIsTaken($dto->organizer_id, $name, $dto->template_id)) {
            throw new ResourceConflictException(__('You already have a template called ":name". Choose another name.', ['name' => $name]));
        }

        if ($dto->template_id === null && $this->templateRepository->countWhere([
            CrmCampaignTemplateDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]) >= self::MAX_TEMPLATES_PER_ORGANIZER) {
            throw new ResourceConflictException(__('You have reached the maximum of :max templates. Delete one to save another.', ['max' => self::MAX_TEMPLATES_PER_ORGANIZER]));
        }

        $attributes = [
            CrmCampaignTemplateDomainObjectAbstract::NAME => $name,
            CrmCampaignTemplateDomainObjectAbstract::SUBJECT => $this->nullable($dto->subject),
            CrmCampaignTemplateDomainObjectAbstract::PREHEADER => $this->nullable($dto->preheader),
            CrmCampaignTemplateDomainObjectAbstract::BODY => (string) $this->sanitizer->sanitize($dto->body),
        ];

        if ($dto->template_id !== null) {
            return $this->templateRepository->updateFromArray($dto->template_id, $attributes);
        }

        return $this->templateRepository->create([
            ...$attributes,
            CrmCampaignTemplateDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            CrmCampaignTemplateDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);
    }

    private function nullable(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
