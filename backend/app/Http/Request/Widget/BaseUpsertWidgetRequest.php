<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Widget;

use HiEvents\DomainObjects\Enums\WidgetType;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\WidgetSettingsRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class BaseUpsertWidgetRequest extends BaseRequest
{
    /**
     * @return string[]
     */
    abstract protected function allowedTypes(): array;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::in($this->allowedTypes())],
            ...WidgetSettingsRules::rules($this->widgetType()),
        ];
    }

    public function messages(): array
    {
        return WidgetSettingsRules::messages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            WidgetSettingsRules::rejectDuplicateSections($validator, $this->input('settings.sections'));
        });
    }

    public function widgetType(): ?WidgetType
    {
        $type = $this->input('type');

        return is_string($type) && in_array($type, $this->allowedTypes(), true)
            ? WidgetType::from($type)
            : null;
    }
}
