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

class InvoiceLineIndexTest extends TestCase
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
     * Test user can list invoice lines for an invoice
     */
    public function test_user_can_list_invoice_lines(): void
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

        InvoiceLine::factory()->count(3)->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.lines.index', [$this->company->id, $invoice->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can view a specific invoice line
     */
    public function test_user_can_view_invoice_line(): void
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
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.lines.show', [$this->company->id, $invoice->id, $line->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invoice_id',
                    'title',
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
                    'id' => $line->id,
                    'invoice_id' => $invoice->id,
                    'title' => 'Test Line',
                ],
            ]);
    }

    /**
     * Test invoice lines require authentication
     */
    public function test_invoice_lines_require_authentication(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->getJson(route('companies.invoices.lines.index', [$this->company->id, $invoice->id]));
        $response->assertStatus(401);
    }

    /**
     * Test user cannot access invoice lines from other company
     */
    public function test_user_cannot_access_invoice_lines_from_other_company(): void
    {
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.lines.index', [$otherCompany->id, $invoice->id]));

        $response->assertStatus(403);
    }

    /**
     * Test user cannot access invoice line from different company
     */
    public function test_user_cannot_access_invoice_line_from_different_company(): void
    {
        $otherCompany = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.lines.show', [$otherCompany->id, $invoice->id, $line->id]));

        $response->assertStatus(403);
    }
}

