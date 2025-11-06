<?php

namespace Tests\Feature\QuoteLine;

use App\Enums\CompanyType;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteLineUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected string $token;

    /**
     * Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = $this->createIssuerCompanyWithUser($this->user);
        $this->token = $this->getAuthToken($this->user);
    }

    /**
     * Test user can update a quote line
     */
    public function test_user_can_update_quote_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-UPDATE-001',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'title' => 'Original Title',
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
            'total_ht' => 500.00,
            'total_tax' => 100.00,
            'total_ttc' => 600.00,
        ]);

        // Update quote totals initially
        $quote->calculateTotals();
        $quote->save();

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.lines.update', [$this->company->id, $quote->id, $line->id]), [
                'title' => 'Updated Title',
                'quantity' => 10,
                'unit_price' => 150.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $line->id,
                    'title' => 'Updated Title',
                    'quantity' => '10.000',
                    'unit_price' => '150.00',
                ],
            ]);

        // Verify line totals are recalculated
        $responseData = $response->json('data');
        $this->assertEquals('1500.00', $responseData['total_ht']); // 10 * 150
        $this->assertEquals('300.00', $responseData['total_tax']); // 1500 * 20%
        $this->assertEquals('1800.00', $responseData['total_ttc']); // 1500 + 300

        // Verify quote totals are updated
        $quote->refresh();
        $this->assertEquals(1500.00, $quote->total_ht);
        $this->assertEquals(300.00, $quote->total_tva);
        $this->assertEquals(1800.00, $quote->total_ttc);
    }

    /**
     * Test quote totals are recalculated when updating a line
     */
    public function test_quote_totals_are_recalculated_when_updating_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-UPDATE-002',
        ]);

        $line1 = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line1->calculateTotals();
        $line1->save();

        $line2 = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'quantity' => 3,
            'unit_price' => 200.00,
            'tva_rate' => 10,
        ]);
        $line2->calculateTotals();
        $line2->save();

        // Initial totals: Line 1: 500 HT + 100 TVA, Line 2: 600 HT + 60 TVA = 1100 HT + 160 TVA
        $quote->calculateTotals();
        $quote->save();

        // Update line 1
        $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.lines.update', [$this->company->id, $quote->id, $line1->id]), [
                'quantity' => 8,
                'unit_price' => 120.00,
                'tva_rate' => 20,
            ])
            ->assertStatus(200);

        // Updated totals: Line 1: 960 HT + 192 TVA, Line 2: 600 HT + 60 TVA = 1560 HT + 252 TVA
        $quote->refresh();
        $this->assertEquals(1560.00, $quote->total_ht);
        $this->assertEquals(252.00, $quote->total_tva);
        $this->assertEquals(1812.00, $quote->total_ttc);
    }

    /**
     * Test user can update only quantity
     */
    public function test_user_can_update_only_quantity(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-UPDATE-003',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'title' => 'Test Line',
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line->calculateTotals();
        $line->save();

        $originalTitle = $line->title;
        $originalUnitPrice = $line->unit_price;

        $quote->calculateTotals();
        $quote->save();

        // Update only quantity
        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.lines.update', [$this->company->id, $quote->id, $line->id]), [
                'quantity' => 10,
            ]);

        $response->assertStatus(200);

        // Verify quantity changed and totals recalculated
        $line->refresh();
        $this->assertEquals(10, $line->quantity);
        $this->assertEquals($originalTitle, $line->title);
        $this->assertEquals($originalUnitPrice, $line->unit_price);
        $this->assertEquals(1000.00, $line->total_ht); // 10 * 100
        $this->assertEquals(200.00, $line->total_tax); // 1000 * 20%

        // Verify quote totals updated
        $quote->refresh();
        $this->assertEquals(1000.00, $quote->total_ht);
        $this->assertEquals(200.00, $quote->total_tva);
    }

    /**
     * Test cannot update line for locked quote
     */
    public function test_cannot_update_line_for_locked_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::ACCEPTED->value,
            'number' => 'D-2025-LINE-UPDATE-004',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.lines.update', [$this->company->id, $quote->id, $line->id]), [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_locked',
            ]);
    }

    /**
     * Test quote lines require authentication for update
     */
    public function test_quote_lines_require_authentication_for_update(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-LINE-UPDATE-005',
        ]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->patchJson(route('companies.quotes.lines.update', [$this->company->id, $quote->id, $line->id]), []);
        $response->assertStatus(401);
    }
}

