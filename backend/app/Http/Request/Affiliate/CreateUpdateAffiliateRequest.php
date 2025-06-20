<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Affiliate;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\AffiliateRules;

class CreateUpdateAffiliateRequest extends BaseRequest
{
    public function rules(): array
    {
        return AffiliateRules::createRules();
    }
}