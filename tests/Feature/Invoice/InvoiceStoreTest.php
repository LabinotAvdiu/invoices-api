<?php

namespace Tests\Feature\Invoice;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStoreTest extends TestCase
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
     * Test user can create an invoice with required fields
     */
    public function test_user_can_create_invoice_with_required_fields(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.store', [$this->company->id]), [
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test Street',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
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
                    'company_id' => $this->company->id,
                    'status' => InvoiceStatus::DRAFT->value,
                ],
            ]);
        
        $responseData = $response->json('data');
        // Totals should be 0 by default (calculated from lines, which are empty)
        $this->assertEquals('0.00', $responseData['total_ht']);
        $this->assertEquals('0.00', $responseData['total_tva']);
        $this->assertEquals('0.00', $responseData['total_ttc']);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT->value,
        ]);
    }

    /**
     * Test invoice status defaults to draft when not provided
     */
    public function test_invoice_status_defaults_to_draft(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.store', [$this->company->id]), [
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test Street',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
            ]);

        $response->assertStatus(201);

        $invoiceId = $response->json('data.id');
        
        // Verify status is draft in the response
        $response->assertJson([
            'data' => [
                'status' => InvoiceStatus::DRAFT->value,
            ],
        ]);

        // Verify status is draft in database
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => InvoiceStatus::DRAFT->value,
        ]);

        // Verify the model has the correct enum value
        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(InvoiceStatus::DRAFT, $invoice->status);
    }

    /**
     * Test user can create an invoice with registered customer
     */
    public function test_user_can_create_invoice_with_registered_customer(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.store', [$this->company->id]), [
                'customer_id' => $customer->id,
                'total_ht' => 1500.00,
                'total_tva' => 300.00,
                'total_ttc' => 1800.00,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'company_id' => $this->company->id,
                    'customer_id' => $customer->id,
                ],
            ]);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Test company_id is forced from route parameter
     */
    public function test_company_id_is_forced_from_route(): void
    {
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.store', [$this->company->id]), [
                'company_id' => $otherCompany->id, // Should be ignored
                'customer_name' => 'Test Customer',
                'customer_address' => '123 Test Street',
                'customer_zip' => '75001',
                'customer_city' => 'Paris',
                'customer_country' => 'France',
            ]);

        $response->assertStatus(201);

        $invoiceId = $response->json('data.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'company_id' => $this->company->id, // Should use company from route
        ]);

        $this->assertDatabaseMissing('invoices', [
            'company_id' => $otherCompany->id,
            'id' => $invoiceId,
        ]);
    }

    /**
     * Test invoices require authentication for creation
     */
    public function test_invoices_require_authentication_for_creation(): void
    {
        $response = $this->postJson(route('companies.invoices.store', [$this->company->id]), []);
        $response->assertStatus(401);
    }
}

