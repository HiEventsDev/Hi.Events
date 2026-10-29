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
            'homepage_sections' => ['nullable', 'array', 'max:41'],
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
        $invalidSection = __("Something in your homepage sections couldn't be saved. Reload the page and try again.");

        return [
            'homepage_sections' => $invalidSection,
            'homepage_sections.max' => __('You can add up to :max sections.'),
            'homepage_sections.*.id' => $invalidSection,
            'homepage_sections.*.type' => $invalidSection,
            'homepage_sections.*.enabled' => $invalidSection,
            'homepage_sections.*.heading' => $invalidSection,
            'homepage_sections.*.heading.max' => __('Section headings can be up to :max characters.'),
            'homepage_sections.*.body' => $invalidSection,
            'homepage_sections.*.body.max' => __('Text sections can be up to :max characters.'),
            'homepage_sections.*.faqs' => $invalidSection,
            'homepage_sections.*.faqs.max' => __('An FAQ section can have up to :max questions.'),
            'homepage_sections.*.faqs.*.question' => $invalidSection,
            'homepage_sections.*.faqs.*.question.required' => __('Every FAQ needs a question.'),
            'homepage_sections.*.faqs.*.question.max' => __('FAQ questions can be up to :max characters.'),
            'homepage_sections.*.faqs.*.answer' => $invalidSection,
            'homepage_sections.*.faqs.*.answer.max' => __('FAQ answers can be up to :max characters.'),
            'homepage_sections.*.video_url' => $invalidSection,
            'homepage_sections.*.video_url.url' => __('Use a YouTube or Vimeo link starting with https://'),
            'homepage_sections.*.video_url.max' => __('Video links can be up to :max characters.'),
            'homepage_sections.*.image_ids' => $invalidSection,
            'homepage_sections.*.image_ids.max' => __('A gallery can have up to :max photos.'),
            'homepage_sections.*.image_ids.*.integer' => $invalidSection,
            'homepage_sections.*.sponsors' => $invalidSection,
            'homepage_sections.*.sponsors.max' => __('You can add up to :max sponsors.'),
            'homepage_sections.*.sponsors.*.image_id' => $invalidSection,
            'homepage_sections.*.sponsors.*.image_id.required' => __('Every sponsor needs a logo.'),
            'homepage_sections.*.sponsors.*.name' => $invalidSection,
            'homepage_sections.*.sponsors.*.name.max' => __('Sponsor names can be up to :max characters.'),
            'homepage_sections.*.sponsors.*.url' => $invalidSection,
            'homepage_sections.*.sponsors.*.url.url' => __('Sponsor links must start with https://'),
            'homepage_sections.*.sponsors.*.url.max' => __('Sponsor links can be up to :max characters.'),
        ];
    }
}
