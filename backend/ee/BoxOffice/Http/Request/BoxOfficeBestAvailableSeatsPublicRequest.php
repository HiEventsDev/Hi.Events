<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class BoxOfficeBestAvailableSeatsPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'exclude' => ['array', 'max:200'],
            'exclude.*' => ['string', 'max:24'],
        ];
    }
}
