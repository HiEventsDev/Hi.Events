<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Badge;

use HiEvents\DomainObjects\Enums\BadgeBackMode;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use HiEvents\DomainObjects\Enums\BadgeTextSize;
use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertBadgeDesignRequest extends BaseRequest
{
    public const MAX_LINES = 5;

    public const MAX_RIBBONS = 100;

    public const TEXT_ALIGNMENTS = ['LEFT', 'CENTER'];

    private const HEX_COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function rules(): array
    {
        return [
            'template' => ['required', Rule::in(BadgeTemplate::valuesArray())],
            'size' => ['required', Rule::in(BadgeSize::valuesArray())],
            'custom_width_mm' => ['nullable', 'integer', 'min:40', 'max:220', 'required_if:size,'.BadgeSize::CUSTOM->value],
            'custom_height_mm' => ['nullable', 'integer', 'min:40', 'max:220', 'required_if:size,'.BadgeSize::CUSTOM->value],
            'sheet' => ['required', Rule::in(BadgeSheet::valuesArray())],
            'accent_color' => ['required', 'string', self::HEX_COLOR],
            'background_color' => ['nullable', 'string', self::HEX_COLOR],
            'text_color' => ['nullable', 'string', self::HEX_COLOR],
            'font_family' => ['required', Rule::in(HomepageFontFamily::valuesArray())],
            'text_align' => ['nullable', Rule::in(self::TEXT_ALIGNMENTS)],
            'uppercase_names' => ['required', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.source' => ['required', Rule::in(BadgeFieldSource::valuesArray())],
            'lines.*.size' => ['required', Rule::in(BadgeTextSize::valuesArray())],
            'lines.*.question_id' => ['nullable', 'integer', 'required_if:lines.*.source,'.BadgeFieldSource::QUESTION->value],
            'lines.*.text' => ['nullable', 'string', 'max:80', 'required_if:lines.*.source,'.BadgeFieldSource::CUSTOM_TEXT->value],
            'show_logo' => ['required', 'boolean'],
            'show_qr' => ['required', 'boolean'],
            'show_ribbon' => ['required', 'boolean'],
            'show_seat' => ['required', 'boolean'],
            'show_event_name' => ['required', 'boolean'],
            'show_sponsors' => ['required', 'boolean'],
            'footer_text' => ['nullable', 'string', 'max:120'],
            'ribbons' => ['present', 'array', 'max:'.self::MAX_RIBBONS],
            'ribbons.*.product_id' => ['required', 'integer'],
            'ribbons.*.label' => ['nullable', 'string', 'max:40'],
            'ribbons.*.color' => ['required', 'string', self::HEX_COLOR],
            'back_mode' => ['required', Rule::in(BadgeBackMode::valuesArray())],
            'back_text' => ['nullable', 'string', 'max:600'],
            'fold_over' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ribbons = $this->input('ribbons');
            if (! is_array($ribbons)) {
                return;
            }

            $seen = [];
            foreach ($ribbons as $index => $ribbon) {
                $productId = is_array($ribbon) ? ($ribbon['product_id'] ?? null) : null;
                if (! is_scalar($productId) || (! is_int($productId) && ! ctype_digit((string) $productId))) {
                    continue;
                }

                if (isset($seen[(int) $productId])) {
                    $validator->errors()->add("ribbons.$index.product_id", __('Each ticket can only have one ribbon.'));
                }
                $seen[(int) $productId] = true;
            }
        });
    }

    public function messages(): array
    {
        $colorMessage = __('Please enter a 6-digit hex colour, for example #1f2937.');

        return [
            'accent_color.regex' => $colorMessage,
            'background_color.regex' => $colorMessage,
            'text_color.regex' => $colorMessage,
            'ribbons.*.color.regex' => $colorMessage,
            'lines.*.question_id.required_if' => __('Choose a question for this line.'),
            'lines.*.text.required_if' => __('Enter the text for this line.'),
            'custom_width_mm.required_if' => __('Enter a width for your custom size.'),
            'custom_height_mm.required_if' => __('Enter a height for your custom size.'),
        ];
    }
}
