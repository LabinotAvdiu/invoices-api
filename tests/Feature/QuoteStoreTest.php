<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteStoreTest extends TestCase
{
    use RefreshDatabase;
    use QuoteTestTrait;

    /**
     * Test user can create a quote with required fields
     */
    public function test_user_can_create_quote_with_required_fields(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'number' => 'D-2025-0001',
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test Street',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
                'total_ht' => 1000.00,
                'total_tva' => 200.00,
                'total_ttc' => 1200.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'company_id',
                    'number',
                    'status',
                    'total_ht',
                    'total_tva',
                    'total_ttc',
                ],
            ])
            ->assertJson([
                'data' => [
                    'company_id' => $company->id,
                    'number' => 'D-2025-0001',
                    'status' => QuoteStatus::DRAFT->value,
                ],
            ]);
        
        $responseData = $response->json('data');
        $this->assertEquals('1000.00', $responseData['total_ht']);
        $this->assertEquals('200.00', $responseData['total_tva']);
        $this->assertEquals('1200.00', $responseData['total_ttc']);

        $this->assertDatabaseHas('quotes', [
            'company_id' => $company->id,
            'number' => 'D-2025-0001',
            'status' => QuoteStatus::DRAFT->value,
        ]);
    }

    /**
     * Test user can create a quote with registered customer
     */
    public function test_user_can_create_quote_with_registered_customer(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $token = $this->getAuthToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'number' => 'D-2025-0002',
                'customer_id' => $customer->id,
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'number' => 'D-2025-0002',
                ],
            ]);

        $this->assertDatabaseHas('quotes', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'D-2025-0002',
        ]);
    }

    /**
     * Test company_id is forced from route parameter
     */
    public function test_company_id_is_forced_from_route(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $token = $this->getAuthToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'number' => 'D-2025-0003',
                'company_id' => $otherCompany->id, // Should be ignored
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test Street',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
                'total_ht' => 1000.00,
                'total_tva' => 200.00,
                'total_ttc' => 1200.00,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'company_id' => $company->id, // Should use company from route
            'number' => 'D-2025-0003',
        ]);

        $this->assertDatabaseMissing('quotes', [
            'company_id' => $otherCompany->id,
            'number' => 'D-2025-0003',
        ]);
    }

    /**
     * Test creation requires number
     */
    public function test_creation_requires_number(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'customer_name' => 'Test Customer',
                'total_ht' => 1000.00,
                'total_tva' => 200.00,
                'total_ttc' => 1200.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['number']);
    }

    /**
     * Test creation can work without totals (defaults to 0)
     */
    public function test_creation_can_work_without_totals(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'number' => 'D-2025-0010',
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test St',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('quotes', [
            'number' => 'D-2025-0010',
            'total_ht' => 0.00,
            'total_tva' => 0.00,
            'total_ttc' => 0.00,
        ]);
    }

    /**
     * Test number must be unique per customer
     */
    public function test_number_must_be_unique_per_customer(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $token = $this->getAuthToken($user);

        Quote::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'D-2025-0011',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/companies/{$company->id}/quotes", [
                'number' => 'D-2025-0011',
                'customer_id' => $customer->id,
                'total_ht' => 1000.00,
                'total_tva' => 200.00,
                'total_ttc' => 1200.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['number']);
    }

    /**
     * Test quotes require authentication for creation
     */
    public function test_quotes_require_authentication_for_creation(): void
    {
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $response = $this->postJson("/api/companies/{$company->id}/quotes", []);
        $response->assertStatus(401);
    }
}

