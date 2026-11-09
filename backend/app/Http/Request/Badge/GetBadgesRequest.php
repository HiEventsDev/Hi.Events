<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Badge;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use Illuminate\Validation\Rule;

class GetBadgesRequest extends BaseRequest
{
    private const ID_LIST = 'regex:/^\d+(,\d+)*$/';

    public function rules(): array
    {
        return [
            'attendee_ids' => ['nullable', 'string', 'max:2400', self::ID_LIST],
            'product_ids' => ['nullable', 'string', 'max:2400', self::ID_LIST],
            'order_id' => ['nullable', 'integer'],
            'event_occurrence_id' => ['nullable', 'integer'],
            'only_unprinted' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'sort_by' => ['nullable', Rule::in(BadgeAttendeeQueryDTO::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.BadgeAttendeeQueryDTO::MAX_PER_PAGE],
        ];
    }

    /**
     * @return int[]|null
     */
    public function idList(string $key): ?array
    {
        $value = $this->validated($key);
        if ($value === null || $value === '') {
            return null;
        }

        return array_values(array_unique(array_map('intval', explode(',', $value))));
    }

    public function messages(): array
    {
        return [
            'attendee_ids.regex' => __('Attendee IDs must be a comma-separated list of numbers.'),
            'product_ids.regex' => __('Product IDs must be a comma-separated list of numbers.'),
        ];
    }
}
