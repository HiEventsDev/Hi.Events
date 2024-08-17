<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use HiEvents\Helper\IdHelper;
use HiEvents\Models\Order;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'event_id' => null, // You might want to link this to an Event factory
            'total_before_additions' => $this->faker->randomFloat(2, 0, 1000),
            'total_tax' => $this->faker->randomFloat(2, 0, 100),
            'created_at' => now(),
            'updated_at' => now(),
            'short_id' => IdHelper::shortId(IdHelper::ORDER_PREFIX),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->safeEmail,
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']), // Add more statuses if needed
            'reserved_until' => $this->faker->dateTimeBetween('now', '+1 week'),
            'session_id' => Str::random(40),
            'currency' => $this->faker->currencyCode,
            'public_id' => $this->faker->unique()->randomNumber(),
            'total_service_fee' => $this->faker->randomFloat(2, 0, 100),
            'point_in_time_data' => json_encode(['key' => 'value']), // Modify as needed
            'total_gross' => $this->faker->randomFloat(2, 0, 1000),
            'payment_gateway' => $this->faker->word,
            'promo_code_id' => null, // You might want to link this to a PromoCode factory
            'promo_code' => $this->faker->word,
            'total_refunded' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
