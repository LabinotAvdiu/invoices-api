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

class InvoiceLineDeleteTest extends TestCase
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
     * Test user can delete an invoice line
     */
    public function test_user_can_delete_invoice_line(): void
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
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line->calculateTotals();
        $line->save();

        // Update invoice totals
        $invoice->calculateTotals();
        $invoice->save();

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('invoice_lines', [
            'id' => $line->id,
        ]);

        // Verify invoice totals are reset to 0
        $invoice->refresh();
        $this->assertEquals(0.00, $invoice->total_ht);
        $this->assertEquals(0.00, $invoice->total_tva);
        $this->assertEquals(0.00, $invoice->total_ttc);
    }

    /**
     * Test invoice totals are recalculated when deleting a line
     */
    public function test_invoice_totals_are_recalculated_when_deleting_line(): void
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

        // Delete line 1
        $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line1->id]))
            ->assertStatus(204);

        // Remaining totals: Line 2: 600 HT + 60 TVA
        $invoice->refresh();
        $this->assertEquals(600.00, $invoice->total_ht);
        $this->assertEquals(60.00, $invoice->total_tva);
        $this->assertEquals(660.00, $invoice->total_ttc);
    }

    /**
     * Test user can delete a line from draft invoice
     */
    public function test_user_can_delete_line_from_draft_invoice(): void
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
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('invoice_lines', [
            'id' => $line->id,
        ]);
    }

    /**
     * Test user cannot delete a line from sent invoice
     */
    public function test_user_cannot_delete_line_from_sent_invoice(): void
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
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot delete a line from paid invoice
     */
    public function test_user_cannot_delete_line_from_paid_invoice(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::PAID,
            'is_locked' => true,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot delete a line from canceled invoice
     */
    public function test_user_cannot_delete_line_from_canceled_invoice(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::CANCELED,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test user cannot delete a line from locked draft invoice
     */
    public function test_user_cannot_delete_line_from_locked_draft_invoice(): void
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
            ->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);
    }

    /**
     * Test invoice lines require authentication for deletion
     */
    public function test_invoice_lines_require_authentication_for_deletion(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->deleteJson(route('companies.invoices.lines.destroy', [$this->company->id, $invoice->id, $line->id]));
        $response->assertStatus(401);
    }
}

