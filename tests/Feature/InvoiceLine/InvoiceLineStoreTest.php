<?php

namespace Tests\Feature\InvoiceLine;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLineStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Company $customer;
    protected Invoice $invoice;
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
        
        $this->customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        
        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
            'total_ht' => 0,
            'total_tva' => 0,
            'total_ttc' => 0,
        ]);
    }

    /**
     * Test user can create an invoice line with required fields
     */
    public function test_user_can_create_invoice_line_with_required_fields(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
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
                    'invoice_id',
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
                    'invoice_id' => $this->invoice->id,
                    'title' => 'Service de développement',
                    'quantity' => '10.000',
                    'unit_price' => '100.00',
                    'tva_rate' => '20.00',
                ],
            ]);

        // Verify invoice line totals
        $responseData = $response->json('data');
        $this->assertEquals('1000.00', $responseData['total_ht']); // 10 * 100
        $this->assertEquals('200.00', $responseData['total_tax']); // 1000 * 20%
        $this->assertEquals('1200.00', $responseData['total_ttc']); // 1000 + 200

        // Verify invoice totals are updated
        $this->invoice->refresh();
        $this->assertEquals(1000.00, $this->invoice->total_ht);
        $this->assertEquals(200.00, $this->invoice->total_tva);
        $this->assertEquals(1200.00, $this->invoice->total_ttc);

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $this->invoice->id,
            'title' => 'Service de développement',
            'total_ht' => 1000.00,
            'total_tax' => 200.00,
            'total_ttc' => 1200.00,
        ]);
    }

    /**
     * Test invoice totals are calculated correctly with multiple lines
     */
    public function test_invoice_totals_are_calculated_with_multiple_lines(): void
    {
        // Create first line
        $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
                'title' => 'Line 1',
                'quantity' => 5,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ])
            ->assertStatus(201);

        // Create second line
        $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
                'title' => 'Line 2',
                'quantity' => 3,
                'unit_price' => 200.00,
                'tva_rate' => 10,
            ])
            ->assertStatus(201);

        // Verify invoice totals: Line 1: 500 HT + 100 TVA = 600 TTC, Line 2: 600 HT + 60 TVA = 660 TTC
        // Total: 1100 HT + 160 TVA = 1260 TTC
        $this->invoice->refresh();
        $this->assertEquals(1100.00, $this->invoice->total_ht);
        $this->assertEquals(160.00, $this->invoice->total_tva);
        $this->assertEquals(1260.00, $this->invoice->total_ttc);
    }

    /**
     * Test invoice_id is forced from route parameter
     */
    public function test_invoice_id_is_forced_from_route(): void
    {
        $otherInvoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
                'invoice_id' => $otherInvoice->id, // Should be ignored
                'title' => 'Test Line',
                'quantity' => 1,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $this->invoice->id, // Should use invoice from route
            'title' => 'Test Line',
        ]);

        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $otherInvoice->id,
            'title' => 'Test Line',
        ]);
    }

    /**
     * Test creation requires title
     */
    public function test_creation_requires_title(): void
    {
        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
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
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), [
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
     * Test cannot create line for sent invoice
     */
    public function test_cannot_create_line_for_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::SENT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $invoice->id]), [
                'title' => 'Test Line',
                'quantity' => 1,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test cannot create line for locked draft invoice
     */
    public function test_cannot_create_line_for_locked_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->postJson(route('companies.invoices.lines.store', [$this->company->id, $invoice->id]), [
                'title' => 'Test Line',
                'quantity' => 1,
                'unit_price' => 100.00,
                'tva_rate' => 20,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test invoice lines require authentication for creation
     */
    public function test_invoice_lines_require_authentication_for_creation(): void
    {
        $response = $this->postJson(route('companies.invoices.lines.store', [$this->company->id, $this->invoice->id]), []);
        $response->assertStatus(401);
    }
}

