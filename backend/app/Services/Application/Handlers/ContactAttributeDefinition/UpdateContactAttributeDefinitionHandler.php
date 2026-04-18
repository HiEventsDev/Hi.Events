<?php

namespace HiEvents\Services\Application\Handlers\ContactAttributeDefinition;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DTO\UpsertContactAttributeDefinitionDTO;

readonly class UpdateContactAttributeDefinitionHandler
{
    public function __construct(
        private ContactAttributeDefinitionRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $definitionId, UpsertContactAttributeDefinitionDTO $dto): ContactAttributeDefinitionDomainObject
    {
        $this->repository->findFirstWhere([
            ContactAttributeDefinitionDomainObject::ID => $definitionId,
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $dto->account_id,
        ]);

        $duplicate = $this->repository->findFirstWhere([
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $dto->account_id,
            ContactAttributeDefinitionDomainObject::NAME => $dto->name,
        ]);

        if ($duplicate !== null && $duplicate->getId() !== $definitionId) {
            throw new ResourceConflictException(
                __('An attribute definition with this name already exists.')
            );
        }

        $updates = [
            ContactAttributeDefinitionDomainObject::NAME => $dto->name,
            ContactAttributeDefinitionDomainObject::LABEL => $dto->label,
            ContactAttributeDefinitionDomainObject::TYPE => $dto->type,
        ];

        if ($dto->wasProvided('options')) {
            $updates[ContactAttributeDefinitionDomainObject::OPTIONS] = $dto->options;
        }
        if ($dto->wasProvided('sort_order')) {
            $updates[ContactAttributeDefinitionDomainObject::SORT_ORDER] = $dto->sort_order;
        }
        if ($dto->wasProvided('is_active')) {
            $updates[ContactAttributeDefinitionDomainObject::IS_ACTIVE] = $dto->is_active;
        }

        $this->repository->updateFromArray($definitionId, $updates);

        return $this->repository->findById($definitionId);
    }
}
