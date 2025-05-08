<?php

declare(strict_types=1);

namespace Database\Factories;

use HiEvents\Helper\IdHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\HiEvents\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = include base_path('data/currencies.php');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => fake()->timezone(),
            'currency_code' => fake()->randomElement(array_values($currencies)),
            'short_id' => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
            'account_configuration_id' => 1, // Default account configuration is first entry
        ];
    }

    /**
     * Indicate that the model's stripe account id is set.
     */
    public function stripeAccount(): self
    {
        return $this->state(fn(array $attributes) => [
            'stripe_account_id' => fake()->stripeConnectAccountId(),
        ]);
    }

    /**
     * Indicate that the model's stripe account connection setup is complete.
     */
    public function stripeConnectSetupComplete(bool $isComplete = true): self
    {
        return $this->state(fn(array $attributes) => [
            'stripe_connect_setup_complete' => $isComplete,
        ]);
    }

    /**
     * Indicate that the model is verified.
     */
    public function verified(): self
    {
        return $this->state(fn(array $attributes) => [
            'account_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the model has been manually verified.
     */
    public function manuallyVerified(): self
    {
        return $this->state(fn(array $attributes) => [
            'is_manually_verified' => true,
        ]);
    }
}
