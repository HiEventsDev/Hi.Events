<?php

namespace HiEvents\Services\Domain\Auth\Access\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class AccountUserAccessData extends BaseDataObject
{
    /**
     * @param  int[]  $organizer_ids
     * @param  int[]  $event_ids
     * @param  array<string, bool>  $permissions
     */
    public function __construct(
        #[WithCast(EnumCast::class)]
        public AccountUserAccessScope $scope = AccountUserAccessScope::ACCOUNT,
        public array $organizer_ids = [],
        public array $event_ids = [],
        public array $permissions = [],
    ) {}

    public static function buildFromValidated(array $validated): self
    {
        return self::from([
            'scope' => $validated['access_scope'] ?? AccountUserAccessScope::ACCOUNT->value,
            'organizer_ids' => $validated['organizer_ids'] ?? [],
            'event_ids' => $validated['event_ids'] ?? [],
            'permissions' => $validated['permissions'] ?? [],
        ]);
    }
}
