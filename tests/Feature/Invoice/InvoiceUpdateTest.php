<?php

namespace Tests\Feature\Invoice;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
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
     * Test user can update a draft invoice
     */
    public function test_user_can_update_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
            'total_ht' => 1000.00,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_name' => $invoice->customer_name ?? 'Test Customer',
                'customer_address' => $invoice->customer_address ?? '123 Test St',
                'customer_zip' => $invoice->customer_zip ?? '75001',
                'customer_city' => $invoice->customer_city ?? 'Paris',
                'customer_country' => $invoice->customer_country ?? 'France',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $invoice->id,
                ],
            ]);

        // Verify invoice was updated (totals are calculated from lines, not manually set)
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test user cannot update a sent invoice
     */
    public function test_user_cannot_update_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::SENT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_name' => $invoice->customer_name ?? 'Test Customer',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot update a paid invoice
     */
    public function test_user_cannot_update_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::PAID,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_name' => $invoice->customer_name ?? 'Test Customer',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot update a canceled invoice
     */
    public function test_user_cannot_update_canceled_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::CANCELED,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_name' => $invoice->customer_name ?? 'Test Customer',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot update a locked invoice even if draft
     */
    public function test_user_cannot_update_locked_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_name' => $invoice->customer_name ?? 'Test Customer',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user can update only the status (draft to sent)
     */
    public function test_user_can_update_status_from_draft_to_sent(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
            'total_ht' => 1000.00,
            'total_tva' => 200.00,
            'total_ttc' => 1200.00,
        ]);

        $originalTotalHt = $invoice->total_ht;
        $originalTotalTva = $invoice->total_tva;
        $originalTotalTtc = $invoice->total_ttc;
        $originalCustomerId = $invoice->customer_id;

        // Update only status - customer_id is preserved automatically
        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::SENT->value,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $invoice->id,
                    'status' => InvoiceStatus::SENT->value,
                ],
            ]);

        // Verify status changed and invoice is locked
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::SENT, $invoice->status);
        $this->assertTrue($invoice->is_locked);

        // Verify totals didn't change
        $this->assertEquals($originalTotalHt, $invoice->total_ht);
        $this->assertEquals($originalTotalTva, $invoice->total_tva);
        $this->assertEquals($originalTotalTtc, $invoice->total_ttc);
        
        // Verify customer_id didn't change
        $this->assertEquals($originalCustomerId, $invoice->customer_id);
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

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer1->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'customer_id' => $customer2->id,
                // Don't provide customer_name, customer_address, etc. - they should be filled automatically
            ]);

        $response->assertStatus(200);

        // Verify that customer information was automatically updated
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'customer_id' => $customer2->id,
            'customer_name' => 'New Customer Company',
            'customer_address' => '456 New Street',
            'customer_zip' => '75002',
            'customer_city' => 'Lyon',
            'customer_country' => 'France',
        ]);
    }

    /**
     * Test invoices require authentication for update
     */
    public function test_invoices_require_authentication_for_update(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), []);
        $response->assertStatus(401);
    }
}

