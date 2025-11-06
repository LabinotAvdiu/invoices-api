<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteIndexTest extends TestCase
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
     * Test user can list quotes for a company
     */
    public function test_user_can_list_quotes(): void
    {
        Quote::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'number' => fn() => 'D-2025-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.index', [$this->company->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'company_id',
                        'customer_id',
                        'customer_name',
                        'number',
                        'status',
                        'total_ht',
                        'total_tva',
                        'total_ttc',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can view a specific quote
     */
    public function test_user_can_view_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-0004',
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.quotes.show', [$this->company->id, $quote->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'company_id',
                    'number',
                    'status',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $quote->id,
                    'company_id' => $this->company->id,
                    'number' => 'D-2025-0004',
                ],
            ]);
    }

    /**
     * Test quotes require authentication
     */
    public function test_quotes_require_authentication(): void
    {
        $response = $this->getJson(route('companies.quotes.index', [$this->company->id]));
        $response->assertStatus(401);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-TEST-AUTH',
        ]);
        $response = $this->getJson(route('companies.quotes.show', [$this->company->id, $quote->id]));
        $response->assertStatus(401);
    }

    /**
     * Test user cannot access quotes from company they don't belong to
     */
    public function test_user_cannot_access_quotes_from_other_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $company->users()->attach($otherUser->id); // Other user's company

        $token = $this->getAuthToken($user);

        $response = $this->authenticated($token)
            ->getJson(route('companies.quotes.index', [$company->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'company_access_denied',
            ]);
    }

    /**
     * Test user cannot access quotes from customer company (must be issuer)
     */
    public function test_user_cannot_access_quotes_from_customer_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $company->users()->attach($user->id);

        $token = $this->getAuthToken($user);

        $response = $this->authenticated($token)
            ->getJson(route('companies.quotes.index', [$company->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'company_must_be_issuer',
            ]);
    }

    /**
     * Test user cannot access quote that doesn't belong to company
     */
    public function test_user_cannot_access_quote_from_different_company(): void
    {
        $user = User::factory()->create();
        $company1 = $this->createIssuerCompanyWithUser($user);
        $company2 = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $company2->users()->attach($user->id);

        $quote = Quote::factory()->create([
            'company_id' => $company2->id,
            'number' => 'D-2025-TEST-SCOPE',
        ]);

        $token = $this->getAuthToken($user);

        // Try to access quote from company2 via company1 route
        $response = $this->authenticated($token)
            ->getJson(route('companies.quotes.show', [$company1->id, $quote->id]));

        $response->assertStatus(404);
    }
}

