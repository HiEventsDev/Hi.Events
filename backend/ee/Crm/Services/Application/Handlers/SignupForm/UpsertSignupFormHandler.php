<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmSignupFormStatus;
use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\UpsertSignupFormDTO;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupFormPublicationGate;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class UpsertSignupFormHandler
{
    public const MAX_FORMS_PER_ORGANIZER = 25;

    public function __construct(
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly CrmTagRepositoryInterface $tagRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly SignupFormPublicationGate $publicationGate,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws UnknownTagException
     */
    public function handle(UpsertSignupFormDTO $dto): CrmSignupFormDomainObject
    {
        $existing = $dto->form_id === null ? null : $this->signupFormRepository->findFirstWhere([
            CrmSignupFormDomainObjectAbstract::ID => $dto->form_id,
            CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);

        if ($dto->form_id !== null && $existing === null) {
            throw new ResourceNotFoundException(__('Signup form not found'));
        }

        if ($dto->status === CrmSignupFormStatus::ACTIVE->value && $existing?->getStatus() !== CrmSignupFormStatus::ACTIVE->value) {
            $this->publicationGate->assertCanGoLive($this->organizerRepository->findFirstWhere([
                OrganizerDomainObjectAbstract::ID => $dto->organizer_id,
                OrganizerDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            ]) ?? throw new ResourceNotFoundException(__('Organizer not found')));
        }

        $tagIds = array_values(array_unique($dto->tag_ids));

        if ($this->tagRepository->countForOrganizer($dto->organizer_id, $tagIds) !== count($tagIds)) {
            throw new UnknownTagException(__('Tag not found'));
        }

        $attributes = [
            CrmSignupFormDomainObjectAbstract::NAME => trim($dto->name),
            CrmSignupFormDomainObjectAbstract::STATUS => $dto->status,
            CrmSignupFormDomainObjectAbstract::TITLE => trim($dto->title),
            CrmSignupFormDomainObjectAbstract::DESCRIPTION => $this->blankToNull($dto->description),
            CrmSignupFormDomainObjectAbstract::BUTTON_LABEL => trim($dto->button_label),
            CrmSignupFormDomainObjectAbstract::SUCCESS_MESSAGE => $this->blankToNull($dto->success_message),
            CrmSignupFormDomainObjectAbstract::ASKS_FOR_NAME => $dto->asks_for_name,
            CrmSignupFormDomainObjectAbstract::PRIVACY_URL => $this->blankToNull($dto->privacy_url),
            CrmSignupFormDomainObjectAbstract::TAG_IDS => $tagIds,
            CrmSignupFormDomainObjectAbstract::SHOW_ON_ORGANIZER_PAGE => $dto->show_on_organizer_page,
        ];

        return $this->databaseManager->transaction(function () use ($dto, $existing, $attributes): CrmSignupFormDomainObject {
            if ($existing !== null) {
                $form = $this->signupFormRepository->updateFromArray($existing->getId(), $attributes);
            } else {
                if ($this->signupFormRepository->countWhere([CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id]) >= self::MAX_FORMS_PER_ORGANIZER) {
                    throw new ResourceConflictException(__('You have reached the maximum number of signup forms.'));
                }

                $form = $this->signupFormRepository->create([
                    ...$attributes,
                    CrmSignupFormDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                    CrmSignupFormDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                    CrmSignupFormDomainObjectAbstract::PUBLIC_ID => 'sf_'.Str::lower(Str::random(16)),
                ]);
            }

            if ($dto->show_on_organizer_page) {
                $this->signupFormRepository->hideOthersFromOrganizerPage($dto->organizer_id, $form->getId());
            }

            return $form;
        });
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
