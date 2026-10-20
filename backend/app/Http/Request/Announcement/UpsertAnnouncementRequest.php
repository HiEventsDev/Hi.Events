<?php

namespace HiEvents\Http\Request\Announcement;

use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertAnnouncementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => [
                'required',
                'string',
                'max:50000',
                Rule::when(
                    $this->input('display_type') === AnnouncementDisplayType::BANNER->name,
                    ['max:200'],
                ),
            ],
            'status' => ['required', Rule::in(AnnouncementStatus::valuesArray())],
            'display_type' => ['required', Rule::in(AnnouncementDisplayType::valuesArray())],
            'emoji' => ['nullable', 'string', 'max:16', 'required_if:display_type,'.AnnouncementDisplayType::MODAL->name],
            'target_type' => ['required', Rule::in(AnnouncementTargetType::valuesArray())],
            'target_account_ids' => ['array', 'max:500', 'required_if:target_type,'.AnnouncementTargetType::ACCOUNTS->name],
            'target_account_ids.*' => ['integer'],
            'target_user_ids' => ['array', 'max:500', 'required_if:target_type,'.AnnouncementTargetType::USERS->name],
            'target_user_ids.*' => ['integer'],
            'cta_label' => ['nullable', 'string', 'max:60', 'required_with:cta_url'],
            'cta_url' => ['nullable', 'url:http,https', 'max:2048', 'required_with:cta_label'],
        ];
    }
}
