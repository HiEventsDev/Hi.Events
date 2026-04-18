<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;

class ContactUpsertService
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
    ) {}

    public function findOrCreateContact(
        int $accountId,
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ContactDomainObject {
        $existing = $this->contactRepository->findByEmailAndAccountId($email, $accountId);

        if ($existing !== null) {
            $updates = [];
            if ($existing->getFirstName() === null && $firstName !== null) {
                $updates[ContactDomainObject::FIRST_NAME] = $firstName;
            }
            if ($existing->getLastName() === null && $lastName !== null) {
                $updates[ContactDomainObject::LAST_NAME] = $lastName;
            }

            if (! empty($updates)) {
                $this->contactRepository->updateFromArray($existing->getId(), $updates);

                return $this->contactRepository->findById($existing->getId());
            }

            return $existing;
        }

        return $this->contactRepository->create([
            ContactDomainObject::ACCOUNT_ID => $accountId,
            ContactDomainObject::EMAIL => strtolower($email),
            ContactDomainObject::FIRST_NAME => $firstName,
            ContactDomainObject::LAST_NAME => $lastName,
            ContactDomainObject::ATTRIBUTES => [],
            ContactDomainObject::ATTRIBUTES_HISTORY => [],
        ]);
    }

    public function updateContactAttributes(
        ContactDomainObject $contact,
        array $newAttributes,
        int $changedByUserId,
        array $sourceQuestionAnswerIds = [],
    ): ContactDomainObject {
        $currentAttributes = is_array($contact->getAttributes())
            ? $contact->getAttributes()
            : json_decode($contact->getAttributes(), true) ?? [];

        $oldValues = [];
        $newValues = [];
        foreach ($newAttributes as $key => $value) {
            if (! array_key_exists($key, $currentAttributes) || $currentAttributes[$key] !== $value) {
                if (array_key_exists($key, $currentAttributes)) {
                    $oldValues[$key] = $currentAttributes[$key];
                }
                $newValues[$key] = $value;
            }
        }

        $hasValueChanges = ! empty($newValues);
        $hasProcessedIds = ! empty($sourceQuestionAnswerIds);

        if (! $hasValueChanges && ! $hasProcessedIds) {
            return $contact;
        }

        $updates = [];

        if ($hasValueChanges) {
            $mergedAttributes = array_merge($currentAttributes, $newValues);

            $history = is_array($contact->getAttributesHistory())
                ? $contact->getAttributesHistory()
                : json_decode($contact->getAttributesHistory(), true) ?? [];

            $history[] = [
                'changed_at' => now()->toIso8601String(),
                'changed_by' => $changedByUserId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ];

            $updates[ContactDomainObject::ATTRIBUTES] = $mergedAttributes;
            $updates[ContactDomainObject::ATTRIBUTES_HISTORY] = $history;
        }

        if ($hasProcessedIds) {
            $existingProcessed = $contact->getProcessedQuestionAnswerIds();
            $existing = is_array($existingProcessed)
                ? $existingProcessed
                : json_decode($existingProcessed ?: '[]', true) ?? [];

            $merged = array_values(array_unique(array_map('intval', array_merge(
                $existing,
                $sourceQuestionAnswerIds,
            ))));

            $updates[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] = $merged;
        }

        $this->contactRepository->updateFromArray($contact->getId(), $updates);

        return $this->contactRepository->findById($contact->getId());
    }
}
