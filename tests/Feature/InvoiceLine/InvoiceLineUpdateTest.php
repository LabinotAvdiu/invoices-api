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

class InvoiceLineUpdateTest extends TestCase
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
     * Test user can update an invoice line
     */
    public function test_user_can_update_invoice_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Original Title',
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line->calculateTotals();
        $line->save();

        // Update invoice totals initially
        $invoice->calculateTotals();
        $invoice->save();

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line->id]), [
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

        // Verify invoice totals are updated
        $invoice->refresh();
        $this->assertEquals(1500.00, $invoice->total_ht);
        $this->assertEquals(300.00, $invoice->total_tva);
        $this->assertEquals(1800.00, $invoice->total_ttc);
    }

    /**
     * Test invoice totals are recalculated when updating a line
     */
    public function test_invoice_totals_are_recalculated_when_updating_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $line1 = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line1->calculateTotals();
        $line1->save();

        $line2 = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 3,
            'unit_price' => 200.00,
            'tva_rate' => 10,
        ]);
        $line2->calculateTotals();
        $line2->save();

        // Initial totals: Line 1: 500 HT + 100 TVA, Line 2: 600 HT + 60 TVA = 1100 HT + 160 TVA
        $invoice->calculateTotals();
        $invoice->save();

        // Update line 1
        $this->authenticated($this->token)
            ->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line1->id]), [
                'quantity' => 8,
                'unit_price' => 120.00,
                'tva_rate' => 20,
            ])
            ->assertStatus(200);

        // Updated totals: Line 1: 960 HT + 192 TVA, Line 2: 600 HT + 60 TVA = 1560 HT + 252 TVA
        $invoice->refresh();
        $this->assertEquals(1560.00, $invoice->total_ht);
        $this->assertEquals(252.00, $invoice->total_tva);
        $this->assertEquals(1812.00, $invoice->total_ttc);
    }

    /**
     * Test user can update only quantity
     */
    public function test_user_can_update_only_quantity(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Test Line',
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line->calculateTotals();
        $line->save();

        $originalTitle = $line->title;
        $originalUnitPrice = $line->unit_price;

        $invoice->calculateTotals();
        $invoice->save();

        // Update only quantity
        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line->id]), [
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

        // Verify invoice totals updated
        $invoice->refresh();
        $this->assertEquals(1000.00, $invoice->total_ht);
        $this->assertEquals(200.00, $invoice->total_tva);
    }

    /**
     * Test cannot update line for sent invoice
     */
    public function test_cannot_update_line_for_sent_invoice(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::SENT,
            'is_locked' => true,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line->id]), [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test cannot update line for locked draft invoice
     */
    public function test_cannot_update_line_for_locked_draft_invoice(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => true,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line->id]), [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test invoice lines require authentication for update
     */
    public function test_invoice_lines_require_authentication_for_update(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->putJson(route('companies.invoices.lines.update', [$this->company->id, $invoice->id, $line->id]), []);
        $response->assertStatus(401);
    }
}

