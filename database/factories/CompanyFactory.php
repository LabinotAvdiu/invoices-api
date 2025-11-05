<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([CompanyType::ISSUER->value, CompanyType::CUSTOMER->value]),
            'name' => fake()->company(),
            'legal_form' => fake()->randomElement(['SARL', 'SAS', 'SA', 'Auto-entrepreneur', 'EURL', 'SNC', 'SCI']),
            'siret' => fake()->numerify('##############'), // 14 digits
            'address' => fake()->streetAddress(),
            'zip_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => fake()->optional()->country(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'creation_date' => fake()->optional()->date(),
            'sector' => fake()->optional()->words(2, true),
        ];
    }
}

