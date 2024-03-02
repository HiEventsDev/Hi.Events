<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Event;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\EventRules;

class CreateEventRequest extends BaseRequest
{
    use EventRules;

    public function rules(): array
    {
        return $this->eventRules();
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
