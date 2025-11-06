<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => null,
            'customer_name' => fake()->company(),
            'customer_address' => fake()->streetAddress(),
            'customer_zip' => fake()->postcode(),
            'customer_city' => fake()->city(),
            'customer_country' => fake()->country(),
            'number' => 'D-' . fake()->year() . '-' . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => QuoteStatus::DRAFT->value,
            'issue_date' => fake()->optional()->date(),
            'valid_until' => fake()->optional()->date(),
            'total_ht' => fake()->randomFloat(2, 100, 10000),
            'total_tva' => fake()->randomFloat(2, 10, 2000),
            'total_ttc' => fake()->randomFloat(2, 110, 12000),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the quote has a registered customer.
     */
    public function withCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => Company::factory(),
            'customer_name' => null,
            'customer_address' => null,
            'customer_zip' => null,
            'customer_city' => null,
            'customer_country' => null,
        ]);
    }

    /**
     * Indicate that the quote has a specific status.
     */
    public function withStatus(QuoteStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status->value,
        ]);
    }
}
