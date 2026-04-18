<?php

namespace HiEvents\Services\Application\Handlers\ContactAttributeDefinition;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DTO\UpsertContactAttributeDefinitionDTO;

readonly class CreateContactAttributeDefinitionHandler
{
    public function __construct(
        private ContactAttributeDefinitionRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(UpsertContactAttributeDefinitionDTO $dto): ContactAttributeDefinitionDomainObject
    {
        $existing = $this->repository->findFirstWhere([
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $dto->account_id,
            ContactAttributeDefinitionDomainObject::NAME => $dto->name,
        ]);

        if ($existing !== null) {
            throw new ResourceConflictException(
                __('An attribute definition with this name already exists.')
            );
        }

        return $this->repository->create([
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $dto->account_id,
            ContactAttributeDefinitionDomainObject::NAME => $dto->name,
            ContactAttributeDefinitionDomainObject::LABEL => $dto->label,
            ContactAttributeDefinitionDomainObject::TYPE => $dto->type,
            ContactAttributeDefinitionDomainObject::OPTIONS => $dto->wasProvided('options') ? $dto->options : null,
            ContactAttributeDefinitionDomainObject::SORT_ORDER => $dto->wasProvided('sort_order') ? $dto->sort_order : 0,
            ContactAttributeDefinitionDomainObject::IS_ACTIVE => $dto->wasProvided('is_active') ? $dto->is_active : true,
        ]);
    }
}
