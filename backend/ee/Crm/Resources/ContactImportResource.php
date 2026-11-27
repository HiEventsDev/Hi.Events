<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmImportDomainObject
 */
class ContactImportResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'file_name' => $this->getFileName(),
            /** @var 'CONTACTS'|'SUPPRESSIONS' */
            'kind' => $this->getKind(),
            /** @var 'PROCESSING'|'COMPLETED'|'FAILED' */
            'status' => $this->getStatus(),
            'consent_attested' => $this->getConsentAttested(),
            'total_rows' => $this->getTotalRows(),
            'created_count' => $this->getCreatedCount(),
            'updated_count' => $this->getUpdatedCount(),
            'skipped_count' => $this->getSkippedCount(),
            'user_name' => $this->getUserName(),
            /** @var array{invalid_email: int, duplicate_in_file: int, previously_deleted: int, already_suppressed: int, bounced_secondary_address: int}|null */
            'skipped_reasons' => $this->skippedReasons(),
            'created_at' => $this->getCreatedAt(),
        ];
    }

    private function skippedReasons(): ?array
    {
        $reasons = $this->getSkippedReasons();

        if (! is_array($reasons)) {
            return null;
        }

        return [
            'invalid_email' => (int) ($reasons['invalid_email'] ?? 0),
            'duplicate_in_file' => (int) ($reasons['duplicate_in_file'] ?? 0),
            'previously_deleted' => (int) ($reasons['previously_deleted'] ?? 0),
            'already_suppressed' => (int) ($reasons['already_suppressed'] ?? 0),
            'bounced_secondary_address' => (int) ($reasons['bounced_secondary_address'] ?? 0),
        ];
    }
}
