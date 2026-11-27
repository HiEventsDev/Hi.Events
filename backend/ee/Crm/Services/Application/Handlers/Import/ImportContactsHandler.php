<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\DomainObjects\Enums\CrmImportKind;
use HiEvents\DomainObjects\Generated\CrmTagDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\UpsertTagHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportParser;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ContactImportRunDTO;
use Throwable;

class ImportContactsHandler
{
    public function __construct(
        private readonly ContactImportParser $parser,
        private readonly ContactImportRunner $runner,
        private readonly CrmTagRepositoryInterface $tagRepository,
    ) {}

    /**
     * @throws InvalidContactImportException
     * @throws Throwable
     */
    public function handle(ImportContactsDTO $dto): CrmImportDomainObject
    {
        $parsed = $this->parser->parse($dto->file_path);
        $tagId = $this->resolveTagId($dto);

        return $this->runner->start(
            new ContactImportRunDTO(
                account_id: $dto->account_id,
                organizer_id: $dto->organizer_id,
                user_id: $dto->user_id,
                file_name: $dto->file_name,
                kind: CrmImportKind::CONTACTS,
                consent_attested: $dto->consent_attested,
                tag_id: $tagId,
            ),
            $parsed,
        );
    }

    /**
     * @throws InvalidContactImportException
     */
    private function resolveTagId(ImportContactsDTO $dto): ?int
    {
        $name = trim((string) $dto->tag_name);

        if ($name === '') {
            return null;
        }

        $existing = $this->tagRepository->findIdByName($dto->organizer_id, $name);

        if ($existing !== null) {
            return $existing;
        }

        if ($this->tagRepository->countWhere([CrmTagDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id]) >= UpsertTagHandler::MAX_TAGS_PER_ORGANIZER) {
            throw new InvalidContactImportException(__('You have reached the maximum number of tags. Choose an existing tag or delete one first.'));
        }

        return $this->tagRepository->create([
            CrmTagDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            CrmTagDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            CrmTagDomainObjectAbstract::NAME => mb_substr($name, 0, 50),
        ])->getId();
    }
}
