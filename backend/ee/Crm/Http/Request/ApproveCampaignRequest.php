<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class ApproveCampaignRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reviewed_version' => ['required', 'date'],
        ];
    }
}
