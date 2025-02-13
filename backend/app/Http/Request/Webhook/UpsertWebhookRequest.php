<?php

namespace HiEvents\Http\Request\Webhook;

use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertWebhookRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'url' => 'required|url',
            'event_types.*' => ['required', Rule::in(WebhookEventType::valuesArray())],
            'status' => ['nullable', Rule::in(WebhookStatus::valuesArray())],
        ];
    }
}
