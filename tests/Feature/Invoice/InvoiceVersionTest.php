<?php

namespace Tests\Feature\Invoice;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceVersionTest extends TestCase
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
     * Test that a version is created when invoice status changes from draft to sent
     */
    public function test_version_is_created_when_invoice_status_changes_from_draft_to_sent(): void
    {
        // Create an invoice with draft status
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'customer_name' => 'Test Customer',
            'total_ht' => 1000.00,
            'total_tva' => 200.00,
            'total_ttc' => 1200.00,
        ]);

        // Create some invoice lines
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Test Line 1',
            'quantity' => 1,
            'unit_price' => 500.00,
            'total_ht' => 500.00,
            'total_tax' => 100.00,
            'total_ttc' => 600.00,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Test Line 2',
            'quantity' => 2,
            'unit_price' => 250.00,
            'total_ht' => 500.00,
            'total_tax' => 100.00,
            'total_ttc' => 600.00,
        ]);

        // Verify no version exists yet
        $this->assertDatabaseMissing('invoice_versions', [
            'invoice_id' => $invoice->id,
        ]);

        // Update invoice status from draft to sent
        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::SENT->value,
            ]);

        $response->assertStatus(200);

        // Verify invoice is now sent and locked
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::SENT, $invoice->status);
        $this->assertTrue($invoice->is_locked);

        // Verify a version was created
        $this->assertDatabaseHas('invoice_versions', [
            'invoice_id' => $invoice->id,
        ]);

        // Get the version and verify its content
        $version = InvoiceVersion::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($version);
        $this->assertNotNull($version->snapshot_data);

        // Verify snapshot contains invoice data
        $snapshot = $version->snapshot_data;
        $this->assertArrayHasKey('invoice', $snapshot);
        $this->assertArrayHasKey('lines', $snapshot);

        // Verify invoice snapshot data
        $invoiceSnapshot = $snapshot['invoice'];
        $this->assertEquals($invoice->id, $invoiceSnapshot['id']);
        $this->assertEquals($invoice->company_id, $invoiceSnapshot['company_id']);
        $this->assertEquals($invoice->customer_name, $invoiceSnapshot['customer_name']);
        $this->assertEquals('1000.00', $invoiceSnapshot['total_ht']);
        $this->assertEquals('200.00', $invoiceSnapshot['total_tva']);
        $this->assertEquals('1200.00', $invoiceSnapshot['total_ttc']);
        $this->assertEquals(InvoiceStatus::DRAFT->value, $invoiceSnapshot['status']); // Should be draft in snapshot

        // Verify lines snapshot data
        $linesSnapshot = $snapshot['lines'];
        $this->assertCount(2, $linesSnapshot);
        $this->assertEquals('Test Line 1', $linesSnapshot[0]['title']);
        $this->assertEquals('Test Line 2', $linesSnapshot[1]['title']);
    }

    /**
     * Test that no version is created when invoice status changes to other statuses
     */
    public function test_no_version_is_created_when_status_changes_to_other_statuses(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        // Change status to paid (not sent)
        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::PAID->value,
            ]);

        $response->assertStatus(200);

        // Verify no version was created
        $this->assertDatabaseMissing('invoice_versions', [
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Test that invoice is locked when status changes from draft to sent
     */
    public function test_invoice_is_locked_when_status_changes_from_draft_to_sent(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        // Update invoice status from draft to sent
        $response = $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::SENT->value,
            ]);

        $response->assertStatus(200);

        // Verify invoice is locked
        $invoice->refresh();
        $this->assertTrue($invoice->is_locked);
        $this->assertEquals(InvoiceStatus::SENT, $invoice->status);
    }

    /**
     * Test that only one version is created even if status changes multiple times
     */
    public function test_only_one_version_is_created_per_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        // First change: draft to sent (should create version)
        $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::SENT->value,
            ]);

        $invoice->refresh();
        $versionCount = InvoiceVersion::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(1, $versionCount);

        // Second change: sent to paid (should NOT create another version)
        $this->authenticated($this->token)
            ->putJson(route('companies.invoices.update', [$this->company->id, $invoice->id]), [
                'status' => InvoiceStatus::PAID->value,
            ]);

        $invoice->refresh();
        $versionCount = InvoiceVersion::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(1, $versionCount); // Still only one version
    }
}

