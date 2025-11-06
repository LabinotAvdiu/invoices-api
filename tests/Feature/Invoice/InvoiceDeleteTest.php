<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDeleteTest extends TestCase
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
     * Test user can delete a draft invoice
     */
    public function test_user_can_delete_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => false,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test user cannot delete a sent invoice
     */
    public function test_user_cannot_delete_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::SENT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test user cannot delete a paid invoice
     */
    public function test_user_cannot_delete_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::PAID,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test user cannot delete a canceled invoice
     */
    public function test_user_cannot_delete_canceled_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::CANCELED,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_can_only_be_deleted_in_draft',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test user cannot delete a locked invoice even if draft
     */
    public function test_user_cannot_delete_locked_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => InvoiceStatus::DRAFT,
            'is_locked' => true,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'invoice_locked',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test invoices require authentication for deletion
     */
    public function test_invoices_require_authentication_for_deletion(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson(route('companies.invoices.destroy', [$this->company->id, $invoice->id]));
        $response->assertStatus(401);
    }
}

