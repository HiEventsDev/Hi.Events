<?php

declare(strict_types=1);

namespace HiEvents\Validators\Rules;

use Illuminate\Validation\Rule;

class HomepageSectionRules
{
    /**
     * @param  string[]  $allowedTypes
     */
    public static function rules(array $allowedTypes): array
    {
        return [
            'homepage_sections' => ['nullable', 'array', 'max:40'],
            'homepage_sections.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'homepage_sections.*.type' => ['required', 'string', Rule::in($allowedTypes)],
            'homepage_sections.*.enabled' => ['boolean'],
            'homepage_sections.*.heading' => ['nullable', 'string', 'max:100'],
            'homepage_sections.*.body' => ['nullable', 'string', 'max:50000'],
            'homepage_sections.*.faqs' => ['nullable', 'array', 'max:50'],
            'homepage_sections.*.faqs.*.question' => ['required', 'string', 'max:300'],
            'homepage_sections.*.faqs.*.answer' => ['nullable', 'string', 'max:5000'],
            'homepage_sections.*.video_url' => ['nullable', 'string', 'url:https', 'max:500'],
            'homepage_sections.*.image_ids' => ['nullable', 'array', 'max:40'],
            'homepage_sections.*.image_ids.*' => ['integer'],
            'homepage_sections.*.sponsors' => ['nullable', 'array', 'max:60'],
            'homepage_sections.*.sponsors.*.image_id' => ['required', 'integer'],
            'homepage_sections.*.sponsors.*.name' => ['nullable', 'string', 'max:100'],
            'homepage_sections.*.sponsors.*.url' => ['nullable', 'string', 'url:https', 'max:500'],
        ];
    }

    public static function messages(): array
    {
        return [
            'homepage_sections.*.video_url.url' => __('Use a YouTube or Vimeo link starting with https://'),
            'homepage_sections.*.sponsors.*.url.url' => __('Sponsor links must start with https://'),
        ];
    }
}
