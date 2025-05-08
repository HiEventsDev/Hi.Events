<?php

declare(strict_types=1);

namespace Database\Factories;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Locale;
use HiEvents\Models\Account;
use HiEvents\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\HiEvents\Core\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
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
            'locale' => fake()->randomElement(Locale::getSupportedLocales()),
        ];
    }

    public function pendingEmail(?string $email = null): self
    {
        return $this->state(fn(array $attributes) => [
            'pending_email' => $email ?? fake()->unique()->safeEmail(),
        ]);
    }

    /**
     * Set the user's password.
     */
    public function password(string $password): static
    {
        return $this->state(fn(array $attributes) => [
            'password' => Hash::make($password),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Saves an Account to the database and attaches it to the user.
     */
    public function withAccount(): static
    {
        return $this->afterCreating(function (User $user): void {
            $account = Account::factory()->verified()->create();
            $account->timezone = $user->timezone;
            $account->name = $user->first_name . ($user->last_name ? ' ' . $user->last_name : '');
            $account->email = strtolower($user->email);

            $user->accounts()->attach($account, [
                'role' => Role::ADMIN,
                'status' => UserStatus::ACTIVE,
                'is_account_owner' => true,
            ]);
        });
    }
}
