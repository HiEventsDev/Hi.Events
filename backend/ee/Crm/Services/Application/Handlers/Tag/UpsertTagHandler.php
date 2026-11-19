<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag;

use HiEvents\DomainObjects\CrmTagDomainObject;
use HiEvents\DomainObjects\Generated\CrmTagDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\DTO\UpsertTagDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpsertTagHandler
{
    public const MAX_TAGS_PER_ORGANIZER = 200;

    public function __construct(
        private readonly CrmTagRepositoryInterface $tagRepository,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertTagDTO $dto): CrmTagDomainObject
    {
        $name = trim($dto->name);

        if ($this->tagRepository->nameExists($dto->organizer_id, $name, $dto->tag_id)) {
            throw new ResourceConflictException(__('A tag with this name already exists.'));
        }

        if ($dto->tag_id !== null) {
            $tag = $this->tagRepository->findFirstWhere([
                CrmTagDomainObjectAbstract::ID => $dto->tag_id,
                CrmTagDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            ]);

            if ($tag === null) {
                throw new ResourceNotFoundException(__('Tag not found'));
            }

            $updated = $this->tagRepository->updateFromArray($tag->getId(), [
                CrmTagDomainObjectAbstract::NAME => $name,
                CrmTagDomainObjectAbstract::COLOR => $dto->color,
            ]);

            $this->cacheVersion->bumpAfterCommit($dto->organizer_id);

            return $updated;
        }

        if ($this->tagRepository->countWhere([CrmTagDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id]) >= self::MAX_TAGS_PER_ORGANIZER) {
            throw new ResourceConflictException(__('You have reached the maximum number of tags.'));
        }

        return $this->tagRepository->create([
            CrmTagDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            CrmTagDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            CrmTagDomainObjectAbstract::NAME => $name,
            CrmTagDomainObjectAbstract::COLOR => $dto->color,
        ]);
    }
}
