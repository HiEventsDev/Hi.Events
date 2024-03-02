<?php

namespace HiEvents\Validators\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class RequiredIf implements ValidationRule
{

    public bool $implicit = true;

    private Request $request;

    private string $field;

    private string $message;

    public function __construct(Request $request, string $field, string $message)
    {
        $this->request = $request;
        $this->field = $field;
        $this->message = $message;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null && $this->request->boolean($this->field)) {
            $fail($this->message ?? "$attribute is required");
        }
    }
}
