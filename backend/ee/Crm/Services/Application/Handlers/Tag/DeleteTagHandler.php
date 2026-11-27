<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag;

use HiEvents\DomainObjects\Generated\CrmTagDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class DeleteTagHandler
{
    public function __construct(
        private readonly CrmTagRepositoryInterface $tagRepository,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $tagId): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $tagId): void {
            $usages = $this->tagRepository->findRuleUsageNames($organizerId, $tagId);

            if ($usages !== []) {
                throw new ResourceConflictException(__('This tag is still used in segment or campaign rules: :names. Remove it from those rules first.', [
                    'names' => implode(', ', array_map(static fn (string $name) => '"'.$name.'"', $usages)),
                ]));
            }

            $deleted = $this->tagRepository->deleteWhere([
                CrmTagDomainObjectAbstract::ID => $tagId,
                CrmTagDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            ]);

            if ($deleted === 0) {
                throw new ResourceNotFoundException(__('Tag not found'));
            }

            $this->tagRepository->removeFromSignupForms($organizerId, $tagId);
            $this->cacheVersion->bumpAfterCommit($organizerId);
        });
    }
}
