<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class RejectCampaignRequest extends BaseRequest
{
    public const MAX_NOTE_LENGTH = 500;

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:'.self::MAX_NOTE_LENGTH],
        ];
    }
}
