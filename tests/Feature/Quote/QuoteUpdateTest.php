<?php

namespace Tests\Feature\Quote;

use App\Enums\CompanyType;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteUpdateTest extends TestCase
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
     * Test user can update a draft quote
     */
    public function test_user_can_update_draft_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-0005',
            'total_ht' => 1000.00,
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'number' => 'D-2025-0005',
                'customer_name' => $quote->customer_name ?? 'Test Customer',
                'customer_address' => $quote->customer_address ?? '123 Test St',
                'customer_zip' => $quote->customer_zip ?? '75001',
                'customer_city' => $quote->customer_city ?? 'Paris',
                'customer_country' => $quote->customer_country ?? 'France',
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $quote->id,
                    'total_ht' => '1500.00',
                ],
            ]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'total_ht' => 1500.00,
        ]);
    }

    /**
     * Test user can update a sent quote
     */
    public function test_user_can_update_sent_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'status' => QuoteStatus::SENT->value,
            'number' => 'D-2025-0006',
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'number' => 'D-2025-0006',
                'customer_name' => $quote->customer_name ?? 'Test Customer',
                'customer_address' => $quote->customer_address ?? '123 Test St',
                'customer_zip' => $quote->customer_zip ?? '75001',
                'customer_city' => $quote->customer_city ?? 'Paris',
                'customer_country' => $quote->customer_country ?? 'France',
                'total_ht' => 2000.00,
                'total_tva' => 400.00,
                'total_ttc' => 2400.00,
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test user cannot update an accepted quote
     */
    public function test_user_cannot_update_accepted_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'status' => QuoteStatus::ACCEPTED->value,
            'number' => 'D-2025-0007',
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'number' => 'D-2025-0007',
                'customer_name' => $quote->customer_name ?? 'Test Customer',
                'customer_address' => $quote->customer_address ?? '123 Test St',
                'customer_zip' => $quote->customer_zip ?? '75001',
                'customer_city' => $quote->customer_city ?? 'Paris',
                'customer_country' => $quote->customer_country ?? 'France',
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_locked',
            ]);
    }

    /**
     * Test user cannot update a rejected quote
     */
    public function test_user_cannot_update_rejected_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'status' => QuoteStatus::REJECTED->value,
            'number' => 'D-2025-0008',
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'number' => 'D-2025-0008',
                'customer_name' => $quote->customer_name ?? 'Test Customer',
                'customer_address' => $quote->customer_address ?? '123 Test St',
                'customer_zip' => $quote->customer_zip ?? '75001',
                'customer_city' => $quote->customer_city ?? 'Paris',
                'customer_country' => $quote->customer_country ?? 'France',
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_locked',
            ]);
    }

    /**
     * Test user cannot update an expired quote
     */
    public function test_user_cannot_update_expired_quote(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'status' => QuoteStatus::EXPIRED->value,
            'number' => 'D-2025-0009',
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'number' => 'D-2025-0009',
                'customer_name' => $quote->customer_name ?? 'Test Customer',
                'customer_address' => $quote->customer_address ?? '123 Test St',
                'customer_zip' => $quote->customer_zip ?? '75001',
                'customer_city' => $quote->customer_city ?? 'Paris',
                'customer_country' => $quote->customer_country ?? 'France',
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_locked',
            ]);
    }

    /**
     * Test user can update only the status
     */
    public function test_user_can_update_only_status(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        // Create quote with customer_id to simplify validation
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-STATUS-TEST',
            'total_ht' => 1000.00,
            'total_tva' => 200.00,
            'total_ttc' => 1200.00,
        ]);

        $originalTotalHt = $quote->total_ht;
        $originalTotalTva = $quote->total_tva;
        $originalTotalTtc = $quote->total_ttc;
        $originalCustomerId = $quote->customer_id;

        // Update only status - customer_id is preserved automatically
        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'status' => QuoteStatus::SENT->value,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $quote->id,
                    'status' => QuoteStatus::SENT->value,
                ],
            ]);

        // Verify status changed
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatus::SENT->value,
        ]);

        // Verify totals didn't change
        $quote->refresh();
        $this->assertEquals($originalTotalHt, $quote->total_ht);
        $this->assertEquals($originalTotalTva, $quote->total_tva);
        $this->assertEquals($originalTotalTtc, $quote->total_ttc);
        
        // Verify customer_id didn't change
        $this->assertEquals($originalCustomerId, $quote->customer_id);
    }

    /**
     * Test customer information is automatically filled when customer_id is updated
     */
    public function test_customer_information_is_automatically_filled_when_customer_id_is_updated(): void
    {
        $customer1 = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'Original Customer',
        ]);

        $customer2 = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'New Customer Company',
            'address' => '456 New Street',
            'zip_code' => '75002',
            'city' => 'Lyon',
            'country' => 'France',
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer1->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-UPDATE-CUSTOMER',
        ]);

        $response = $this->authenticated($this->token)
            ->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), [
                'customer_id' => $customer2->id,
                // Don't provide customer_name, customer_address, etc. - they should be filled automatically
            ]);

        $response->assertStatus(200);

        // Verify that customer information was automatically updated
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'customer_id' => $customer2->id,
            'customer_name' => 'New Customer Company',
            'customer_address' => '456 New Street',
            'customer_zip' => '75002',
            'customer_city' => 'Lyon',
            'customer_country' => 'France',
        ]);
    }

    /**
     * Test quotes require authentication for update
     */
    public function test_quotes_require_authentication_for_update(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-TEST-AUTH-UPDATE',
        ]);

        $response = $this->patchJson(route('companies.quotes.update', [$this->company->id, $quote->id]), []);
        $response->assertStatus(401);
    }
}

