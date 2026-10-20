<?php

namespace HiEvents\DomainObjects\Enums;

enum Role: string
{
    use BaseEnum;

    case SUPERADMIN = 'SUPERADMIN';
    case ADMIN = 'ADMIN';
    case ORGANIZER = 'ORGANIZER';
    case VIEWER = 'VIEWER';

    public static function getAssignableRoles(): array
    {
        return [
            self::ADMIN->value,
            self::ORGANIZER->value,
            self::VIEWER->value,
        ];
    }

    /**
     * @return Permission[]
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SUPERADMIN, self::ADMIN => Permission::cases(),
            self::ORGANIZER => [
                Permission::EDIT,
                Permission::VIEW_FINANCIALS,
                Permission::ISSUE_REFUNDS,
            ],
            self::VIEWER => [],
        };
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return Permission[]
     */
    public function permissionsWith(array $overrides): array
    {
        $permissions = $this->defaultPermissions();

        if ($this->canBeRestricted()) {
            foreach (Permission::adjustable() as $permission) {
                if (! array_key_exists($permission->value, $overrides)) {
                    continue;
                }

                $permissions = array_filter($permissions, static fn (Permission $held) => $held !== $permission);

                if ($overrides[$permission->value]) {
                    $permissions[] = $permission;
                }
            }
        }

        return array_values(array_filter(
            $permissions,
            static fn (Permission $permission) => collect($permission->requires())
                ->every(static fn (Permission $required) => in_array($required, $permissions, true)),
        ));
    }

    public function canBeRestricted(): bool
    {
        return in_array($this, [self::ORGANIZER, self::VIEWER], true);
    }
}
