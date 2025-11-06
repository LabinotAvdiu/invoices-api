<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteLineStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Company $customer;
    protected Quote $quote;
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
        
        $this->quote = Quote::factory()
            ->withCustomerCompany()
            ->create([
                'company_id' => $this->company->id,
                'status' => QuoteStatus::DRAFT->value,
                'number' => 'D-2025-LINE-BASE',
                'total_ht' => 0,
                'total_tva' => 0,
                'total_ttc' => 0,
            ]);
        
        // Get the customer from the created quote
        $this->customer = $this->quote->customer;
    }

    /**
     * Test user can create a quote line with required fields
     */
    public function test_user_can_create_quote_line_with_required_fields(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'title' => 'Service de développement',
                'description' => 'Développement d\'une application web',
                'quantity' => 10,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'quote_id',
                    'title',
                    'description',
                    'quantity',
                    'unit_price',
                    'tva_rate',
                    'total_ht',
                    'total_tax',
                    'total_ttc',
                ],
            ])
            ->assertJson([
                'data' => [
                    'quote_id' => $this->quote->id,
                    'title' => 'Service de développement',
                    'quantity' => '10.000',
                    'unit_price' => '100.00',
                    'tva_rate' => '20.00',
                ],
            ]);

        // Verify quote line totals
        $responseData = $response->json('data');
        $this->assertEquals('1000.00', $responseData['total_ht']); // 10 * 100
        $this->assertEquals('200.00', $responseData['total_tax']); // 1000 * 20%
        $this->assertEquals('1200.00', $responseData['total_ttc']); // 1000 + 200

        // Verify quote totals are updated
        $this->quote->refresh();
        $this->assertEquals(1000.00, $this->quote->total_ht);
        $this->assertEquals(200.00, $this->quote->total_tva);
        $this->assertEquals(1200.00, $this->quote->total_ttc);

        $this->assertDatabaseHas('quote_lines', [
            'quote_id' => $this->quote->id,
            'title' => 'Service de développement',
            'total_ht' => 1000.00,
            'total_tax' => 200.00,
            'total_ttc' => 1200.00,
        ]);
    }

    /**
     * Test quote totals are calculated correctly with multiple lines
     */
    public function test_quote_totals_are_calculated_with_multiple_lines(): void
    {
        // Create first line
        $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'title' => 'Line 1',
                'quantity' => 5,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ])
            ->assertStatus(201);

        // Create second line
        $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'title' => 'Line 2',
                'quantity' => 3,
                'unit_price' => 200.00,
                'tva_rate' => 10,
            ])
            ->assertStatus(201);

        // Verify quote totals: Line 1: 500 HT + 100 TVA = 600 TTC, Line 2: 600 HT + 60 TVA = 660 TTC
        // Total: 1100 HT + 160 TVA = 1260 TTC
        $this->quote->refresh();
        $this->assertEquals(1100.00, $this->quote->total_ht);
        $this->assertEquals(160.00, $this->quote->total_tva);
        $this->assertEquals(1260.00, $this->quote->total_ttc);
    }

    /**
     * Test quote_id is forced from route parameter
     */
    public function test_quote_id_is_forced_from_route(): void
    {
        $otherQuote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-004',
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'quote_id' => $otherQuote->id, // Should be ignored
                'title' => 'Test Line',
                'quantity' => 1,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('quote_lines', [
            'quote_id' => $this->quote->id, // Should use quote from route
            'title' => 'Test Line',
        ]);

        $this->assertDatabaseMissing('quote_lines', [
            'quote_id' => $otherQuote->id,
            'title' => 'Test Line',
        ]);
    }

    /**
     * Test creation requires title
     */
    public function test_creation_requires_title(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'quantity' => 1,
                'unit_price' => 100.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test totals are automatically calculated
     */
    public function test_totals_are_automatically_calculated(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), [
                'title' => 'Test Line',
                'quantity' => 2.5,
                'unit_price' => 100.50,
                'tva_rate' => 20,
                // Don't provide totals, they should be calculated
            ]);

        $response->assertStatus(201);

        $responseData = $response->json('data');
        // 2.5 * 100.50 = 251.25 HT
        // 251.25 * 20% = 50.25 TVA
        // 251.25 + 50.25 = 301.50 TTC
        $this->assertEquals('251.25', $responseData['total_ht']);
        $this->assertEquals('50.25', $responseData['total_tax']);
        $this->assertEquals('301.50', $responseData['total_ttc']);
    }

    /**
     * Test cannot create line for locked quote
     */
    public function test_cannot_create_line_for_locked_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::ACCEPTED->value,
            'number' => 'D-2025-LINE-007',
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.quotes.lines.store', [$this->company->id, $quote->id]), [
                'title' => 'Test Line',
                'quantity' => 1,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_locked',
            ]);
    }

    /**
     * Test quote lines require authentication for creation
     */
    public function test_quote_lines_require_authentication_for_creation(): void
    {
        $response = $this->postJson(route('companies.quotes.lines.store', [$this->company->id, $this->quote->id]), []);
        $response->assertStatus(401);
    }
}

