<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Enterprise\Crm\Http\Request\Rules\KnownPlaceholdersRule;
use HiEvents\Http\Request\BaseRequest;

class UpsertCampaignTemplateRequest extends BaseRequest
{
    public function rules(): array
    {
        $placeholders = new KnownPlaceholdersRule(allowsEventPlaceholders: false, isTemplate: true);

        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'subject' => ['nullable', 'string', 'max:200', $placeholders],
            'preheader' => ['nullable', 'string', 'max:150', $placeholders],
            'body' => ['required', 'string', 'min:1', 'max:100000', $placeholders],
        ];
    }
}
