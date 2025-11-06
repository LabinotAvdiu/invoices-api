<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 1000);
        $tvaRate = fake()->randomElement([0, 5.5, 10, 20]);
        
        $totalHt = $quantity * $unitPrice;
        $totalTax = $totalHt * ($tvaRate / 100);
        $totalTtc = $totalHt + $totalTax;

        return [
            'quote_id' => Quote::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tva_rate' => $tvaRate,
            'total_ht' => round($totalHt, 2),
            'total_tax' => round($totalTax, 2),
            'total_ttc' => round($totalTtc, 2),
        ];
    }
}
