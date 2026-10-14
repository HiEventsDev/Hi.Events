<?php

namespace HiEvents\Http\Request\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseRequest
{
    private ?UserDomainObject $targetUser = null;

    public function rules(): array
    {
        return [
            'first_name' => PersonNameRule::rules(required: false, currentValue: $this->targetUser()?->getFirstName()),
            'last_name' => PersonNameRule::rules(required: false, currentValue: $this->targetUser()?->getLastName()),
            'status' => Rule::in([UserStatus::INACTIVE->name, UserStatus::ACTIVE->name]), // don't allow INVITED
            'role' => Rule::in(Role::getAssignableRoles()),
        ];
    }

    private function targetUser(): ?UserDomainObject
    {
        return $this->targetUser ??= app(UserRepositoryInterface::class)->findFirstWhere([
            'id' => $this->route('user_id'),
        ]);
    }
}
