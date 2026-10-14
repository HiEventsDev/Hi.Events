<?php

declare(strict_types=1);

namespace Database\Factories;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\Account;
use HiEvents\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<\HiEvents\Core\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(fake()->password(16)),
            'timezone' => fake()->timezone(),
            'locale' => 'en',
        ];
    }

    public function pendingEmail(?string $email = null): self
    {
        return $this->state(fn (array $attributes) => [
            'pending_email' => $email ?? fake()->unique()->safeEmail(),
        ]);
    }

    public function password(string $password): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make($password),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * @param  string[]  $recoveryCodes
     */
    public function withTwoFactor(string $secret, array $recoveryCodes = []): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(array_map(
                static fn (string $code) => hash('sha256', preg_replace('/[^a-z0-9]/', '', strtolower($code))),
                $recoveryCodes,
            )),
        ]);
    }

    public function withAccount(): static
    {
        return $this->afterCreating(function (User $user): void {
            $account = Account::factory()->verified()->create();
            $account->timezone = $user->timezone;
            $account->name = $user->first_name.($user->last_name ? ' '.$user->last_name : '');
            $account->email = strtolower($user->email);

            $user->accounts()->attach($account, [
                'role' => Role::ADMIN,
                'status' => UserStatus::ACTIVE,
                'is_account_owner' => true,
            ]);
        });
    }
}
