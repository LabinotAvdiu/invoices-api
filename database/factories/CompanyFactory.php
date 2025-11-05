<?php

namespace Database\Factories;

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
            'name' => fake()->company(),
            'legal_form' => fake()->randomElement(['SARL', 'SAS', 'SA', 'Auto-entrepreneur', 'EURL', 'SNC', 'SCI']),
            'siret' => fake()->numerify('##############'), // 14 digits
            'address' => fake()->optional()->streetAddress(),
            'zip_code' => fake()->optional()->postcode(),
            'city' => fake()->optional()->city(),
            'country' => fake()->optional()->country(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'creation_date' => fake()->optional()->date(),
            'sector' => fake()->optional()->words(2, true),
        ];
    }
}

