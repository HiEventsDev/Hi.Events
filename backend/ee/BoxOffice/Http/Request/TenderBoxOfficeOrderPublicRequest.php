<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class TenderBoxOfficeOrderPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tender' => ['required', Rule::in(BoxOfficeTender::CASH->value, BoxOfficeTender::COMP->value, BoxOfficeTender::OTHER->value)],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'reference' => ['nullable', 'string', 'max:120', 'required_if:tender,'.BoxOfficeTender::OTHER->value],
        ];
    }
}
