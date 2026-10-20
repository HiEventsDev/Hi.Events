<?php

namespace HiEvents\Http\Request\User;

use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesAccountUserAccess
{
    private const MAX_GRANTS = 500;

    protected function accessRules(): array
    {
        $adjustable = array_map(static fn (Permission $permission) => $permission->value, Permission::adjustable());

        return [
            'access_scope' => ['nullable', Rule::in(AccountUserAccessScope::valuesArray())],
            ...$this->idListRules('organizer_ids'),
            ...$this->idListRules('event_ids'),
            'permissions' => ['nullable', 'array:'.implode(',', $adjustable)],
            ...array_fill_keys(array_map(static fn (string $permission) => "permissions.$permission", $adjustable), ['boolean']),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') === Role::ADMIN->value && $this->input('access_scope') === AccountUserAccessScope::RESTRICTED->value) {
                $validator->errors()->add('access_scope', __('Admins always have access to the whole account.'));
            }
        });
    }

    private function idListRules(string $field): array
    {
        $rules = [$field => ['nullable', 'array', 'max:'.self::MAX_GRANTS]];

        if (count((array) $this->input($field, [])) <= self::MAX_GRANTS) {
            $rules["$field.*"] = ['integer'];
        }

        return $rules;
    }
}
