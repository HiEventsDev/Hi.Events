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
    public function definition(): array
    {
        $currencies = include base_path('data/currencies.php');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => fake()->timezone(),
            'currency_code' => fake()->randomElement(array_values($currencies)),
            'short_id' => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
            'account_configuration_id' => 1,
        ];
    }

    public function verified(): self
    {
        return $this->state(fn(array $attributes) => [
            'account_verified_at' => now(),
        ]);
    }

    public function manuallyVerified(): self
    {
        return $this->state(fn(array $attributes) => [
            'is_manually_verified' => true,
        ]);
    }
}
