<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\SuppressionImportPreviewDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SuppressionImportPreviewDTO
 */
class SuppressionImportPreviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'duplicate_rows' => $this->duplicate_rows,
            'new_contacts' => $this->new_contacts,
            'existing_contacts' => $this->existing_contacts,
            'subscribed_contacts' => $this->subscribed_contacts,
            'deleted_contacts' => $this->deleted_contacts,
            /** @var array{UNSUBSCRIBED: int, BOUNCED: int, COMPLAINED: int} */
            'reasons' => $this->reasons,
            /** @var array<int, array{email: string, reason: 'UNSUBSCRIBED'|'BOUNCED'|'COMPLAINED'}> */
            'sample' => $this->sample,
        ];
    }
}
