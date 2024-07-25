<?php

namespace HiEvents\Http\Request\CapacityAssigment;

use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertCapacityAssignmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => RulesHelper::REQUIRED_STRING,
            'capacity' => ['nullable', 'numeric', 'min:1'],
            'applies_to' => [Rule::in(CapacityAssignmentAppliesTo::valuesArray())],
            'status' => [Rule::in(CapacityAssignmentStatus::valuesArray())],
            'ticket_ids' => ['nullable', 'required_if:applies_to,' . CapacityAssignmentAppliesTo::TICKETS->name, 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_ids.required_if' => __('Please select at least one ticket.'),
        ];
    }
}
