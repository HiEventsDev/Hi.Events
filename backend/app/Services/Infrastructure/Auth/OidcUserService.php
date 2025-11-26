<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Auth;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Models\UserProvider;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OidcUserService
{
    private const DEFAULT_PROVIDER = 'authentik';

    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    public function findOrCreateUser(object $claims, string $provider = self::DEFAULT_PROVIDER): User
    {
        $providerId = $claims->sub ?? null;

        if ($providerId === null) {
            throw new RuntimeException('OIDC token missing subject (sub) claim');
        }

        $email = strtolower($claims->email ?? '');
        $existing = UserProvider::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing?->user) {
            return $existing->user;
        }

        $user = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            [$firstName, $lastName] = $this->resolveNames($claims, $email);

            $user = User::create([
                'email' => $email ?: sprintf('%s@placeholder.local', $providerId),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => ($claims->email_verified ?? false) ? now() : null,
                'timezone' => config('app.timezone', 'UTC'),
                'locale' => $claims->locale ?? 'en',
            ]);
        }

        UserProvider::updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $user->id,
            ]
        );

        return $user;
    }

    public function buildLoginResponse(User $user): LoginResponse
    {
        $userDomain = UserDomainObject::hydrateFromModel($user);

        $userAccounts = $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: AccountDomainObject::class, name: 'account'))
            ->findWhere([
                'user_id' => $user->id,
            ]);

        $accounts = $userAccounts->map(fn(AccountUserDomainObject $accountUser) => $accountUser->getAccount());
        [$accountId, $role] = $this->getAccountContext($accounts, $userAccounts);

        $claims = [];

        if ($accountId !== null) {
            $claims['account_id'] = $accountId;
            User::setCurrentAccountId($accountId);
        }

        if ($role !== null) {
            $claims['role'] = $role->value;
        }

        $token = auth('api')->claims($claims)->login($user);

        return new LoginResponse(
            accounts: $accounts,
            token: $token,
            user: $userDomain,
            accountId: $accountId,
        );
    }

    /**
     * @return array{int|null, Role|null}
     */
    private function getAccountContext(Collection $accounts, Collection $userAccounts): array
    {
        if ($accounts->count() !== 1) {
            return [null, null];
        }

        $accountId = $accounts->first()?->getId();

        /** @var AccountUserDomainObject|null $pivot */
        $pivot = $userAccounts
            ->first(fn(AccountUserDomainObject $userAccount) => $userAccount->getAccountId() === $accountId);

        return [$accountId, $pivot?->getRole() ? Role::from($pivot->getRole()) : null];
    }

    /**
     * @return array{string, ?string}
     */
    private function resolveNames(object $claims, string $email): array
    {
        if (isset($claims->given_name) || isset($claims->family_name)) {
            return [
                $claims->given_name ?? $email ?: 'Guest',
                $claims->family_name ?? null,
            ];
        }

        if (isset($claims->name)) {
            $parts = explode(' ', (string)$claims->name, 2);
            return [$parts[0], $parts[1] ?? null];
        }

        $firstName = $email ? explode('@', $email)[0] : 'Guest';

        return [$firstName, null];
    }
}
