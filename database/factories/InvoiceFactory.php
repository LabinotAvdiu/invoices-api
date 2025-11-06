<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoice::class;

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
            'customer_email' => fake()->optional()->email(),
            'customer_phone' => fake()->optional()->phoneNumber(),
            // number will be auto-generated if not provided
            'status' => InvoiceStatus::DRAFT->value,
            'issue_date' => fake()->optional()->date(),
            'due_date' => fake()->optional()->date(),
            'is_locked' => false,
            'total_ht' => fake()->randomFloat(2, 100, 10000),
            'total_tva' => fake()->randomFloat(2, 10, 2000),
            'total_ttc' => fake()->randomFloat(2, 110, 12000),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the invoice has a registered customer.
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
            'customer_email' => null,
            'customer_phone' => null,
        ]);
    }

    /**
     * Indicate that the invoice has a specific status.
     */
    public function withStatus(InvoiceStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status->value,
            // Auto-lock if sent or paid
            'is_locked' => in_array($status, [InvoiceStatus::SENT, InvoiceStatus::PAID]),
        ]);
    }

    /**
     * Indicate that the invoice has a registered customer company (type CUSTOMER).
     */
    public function withCustomerCompany(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => Company::factory()->create([
                'type' => CompanyType::CUSTOMER->value,
            ])->id,
            'customer_name' => null,
            'customer_address' => null,
            'customer_zip' => null,
            'customer_city' => null,
            'customer_country' => null,
            'customer_email' => null,
            'customer_phone' => null,
        ]);
    }

    /**
     * Indicate that the invoice is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    /**
     * Indicate that the invoice is unlocked.
     */
    public function unlocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => false,
        ]);
    }
}

