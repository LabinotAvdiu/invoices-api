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

class QuoteLineIndexTest extends TestCase
{
    use RefreshDatabase;
    use QuoteTestTrait;

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
     * Test user can list quote lines for a quote
     */
    public function test_user_can_list_quote_lines(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-INDEX-001',
        ]);

        QuoteLine::factory()->count(3)->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.lines.index', [$this->company->id, $quote->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can view a specific quote line
     */
    public function test_user_can_view_quote_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-INDEX-002',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'title' => 'Test Line',
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.lines.show', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'quote_id',
                    'title',
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
                    'id' => $line->id,
                    'quote_id' => $quote->id,
                    'title' => 'Test Line',
                ],
            ]);
    }

    /**
     * Test quote lines require authentication
     */
    public function test_quote_lines_require_authentication(): void
    {
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-LINE-INDEX-003',
        ]);

        $response = $this->getJson(route('companies.quotes.lines.index', [$this->company->id, $quote->id]));
        $response->assertStatus(401);
    }

    /**
     * Test user cannot access quote lines from other company
     */
    public function test_user_cannot_access_quote_lines_from_other_company(): void
    {
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $otherCompany->id,
            'number' => 'D-2025-LINE-INDEX-004',
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.lines.index', [$otherCompany->id, $quote->id]));

        $response->assertStatus(403);
    }

    /**
     * Test user cannot access quote line from different company
     */
    public function test_user_cannot_access_quote_line_from_different_company(): void
    {
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $otherCompany->id,
            'number' => 'D-2025-LINE-INDEX-005',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.lines.show', [$otherCompany->id, $quote->id, $line->id]));

        $response->assertStatus(403);
    }
}

