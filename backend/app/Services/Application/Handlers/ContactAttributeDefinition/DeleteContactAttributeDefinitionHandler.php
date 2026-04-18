<?php

namespace HiEvents\Services\Application\Handlers\ContactAttributeDefinition;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use Illuminate\Support\Facades\DB;

readonly class DeleteContactAttributeDefinitionHandler
{
    public function __construct(
        private ContactAttributeDefinitionRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $definitionId, int $accountId): void
    {
        $this->repository->findFirstWhere([
            ContactAttributeDefinitionDomainObject::ID => $definitionId,
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $accountId,
        ]);

        $linked = DB::table('questions')
            ->where('contact_attribute_definition_id', $definitionId)
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as question_count, count(distinct event_id) as event_count')
            ->first();

        if ($linked && (int) $linked->question_count > 0) {
            throw new ResourceConflictException(__(
                'In use by :questions question(s) across :events event(s). Unlink or delete those questions first.',
                [
                    'questions' => (int) $linked->question_count,
                    'events' => (int) $linked->event_count,
                ],
            ));
        }

        $this->repository->deleteById($definitionId);
    }
}
