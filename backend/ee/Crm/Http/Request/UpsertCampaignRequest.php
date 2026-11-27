<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Request\Rules\KnownPlaceholdersRule;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsComposer;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertCampaignRequest extends BaseRequest
{
    private const MAX_EMAIL_BODY_LENGTH = 100000;

    public function rules(): array
    {
        $placeholders = new KnownPlaceholdersRule($this->filled('event_id'));

        return [
            'name' => ['required', 'string', 'min:1', 'max:150'],
            'subject' => ['nullable', 'string', 'max:200', $placeholders],
            'preheader' => ['nullable', 'string', 'max:150', $placeholders],
            'channel' => ['nullable', Rule::in(CrmMarketingChannel::valuesArray())],
            'body' => ['nullable', 'string', 'max:'.($this->channel() === CrmMarketingChannel::SMS->value ? CampaignSmsComposer::MAX_BODY_LENGTH : self::MAX_EMAIL_BODY_LENGTH), $placeholders],
            'early_access_product_ids' => ['nullable', 'array', 'max:50'],
            'early_access_product_ids.*' => ['integer', 'min:1'],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'segment_id' => ['nullable', 'integer', 'min:1'],
            'preset' => ['nullable', 'string', 'max:50'],
            'definition' => ['nullable', 'array'],
            'event_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function channel(): ?string
    {
        if ($this->route('campaign_id') === null) {
            return $this->input('channel');
        }

        return app(CrmCampaignRepositoryInterface::class)->findFirstWhere([
            CrmCampaignDomainObjectAbstract::ID => (int) $this->route('campaign_id'),
            CrmCampaignDomainObjectAbstract::ORGANIZER_ID => (int) $this->route('organizer_id'),
        ])?->getChannel();
    }
}
