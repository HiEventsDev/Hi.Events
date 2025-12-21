<?php

declare(strict_types=1);

namespace Database\Factories;

use HiEvents\Models\Account;
use HiEvents\Models\AccountVatSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\HiEvents\Models\AccountVatSetting>
 */
class AccountVatSettingFactory extends Factory
{
    protected $model = AccountVatSetting::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'vat_registered' => fake()->boolean(),
            'vat_number' => null,
            'vat_validated' => false,
            'vat_validation_date' => null,
            'business_name' => null,
            'business_address' => null,
            'vat_country_code' => null,
        ];
    }

    public function registered(): self
    {
        $countryCode = fake()->randomElement(['IE', 'DE', 'FR', 'ES', 'NL', 'IT']);
        $vatNumber = $countryCode . fake()->numerify('########');

        return $this->state(fn(array $attributes) => [
            'vat_registered' => true,
            'vat_number' => $vatNumber,
            'vat_country_code' => $countryCode,
        ]);
    }

    public function validated(): self
    {
        $countryCode = fake()->randomElement(['IE', 'DE', 'FR', 'ES', 'NL', 'IT']);
        $vatNumber = $countryCode . fake()->numerify('########');

        return $this->state(fn(array $attributes) => [
            'vat_registered' => true,
            'vat_number' => $vatNumber,
            'vat_validated' => true,
            'vat_validation_date' => now(),
            'business_name' => fake()->company(),
            'business_address' => fake()->address(),
            'vat_country_code' => $countryCode,
        ]);
    }

    public function notRegistered(): self
    {
        return $this->state(fn(array $attributes) => [
            'vat_registered' => false,
            'vat_number' => null,
            'vat_validated' => false,
            'vat_validation_date' => null,
            'business_name' => null,
            'business_address' => null,
            'vat_country_code' => null,
        ]);
    }
}
