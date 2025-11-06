<?php

namespace Tests\Feature\Invoice;

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIndexTest extends TestCase
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
     * Test user can list invoices for a company
     */
    public function test_user_can_list_invoices(): void
    {
        Invoice::factory()->count(3)->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.index', [$this->company->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'company_id',
                        'customer_id',
                        'customer_name',
                        'number',
                        'status',
                        'total_ht',
                        'total_tva',
                        'total_ttc',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can view a specific invoice
     */
    public function test_user_can_view_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->authenticated($this->token)
            ->getJson(route('companies.invoices.show', [$this->company->id, $invoice->id]));

        $response->assertStatus(200)
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
                    'id' => $invoice->id,
                    'company_id' => $this->company->id,
                ],
            ]);
    }

    /**
     * Test invoices require authentication
     */
    public function test_invoices_require_authentication(): void
    {
        $response = $this->getJson(route('companies.invoices.index', [$this->company->id]));
        $response->assertStatus(401);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $response = $this->getJson(route('companies.invoices.show', [$this->company->id, $invoice->id]));
        $response->assertStatus(401);
    }

    /**
     * Test user cannot access invoices from company they don't belong to
     */
    public function test_user_cannot_access_invoices_from_other_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $company->users()->attach($otherUser->id); // Other user's company

        $token = $this->getAuthToken($user);

        $response = $this->authenticated($token)
            ->getJson(route('companies.invoices.index', [$company->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'company_access_denied',
            ]);
    }

    /**
     * Test user cannot access invoices from customer company (must be issuer)
     */
    public function test_user_cannot_access_invoices_from_customer_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $company->users()->attach($user->id);

        $token = $this->getAuthToken($user);

        $response = $this->authenticated($token)
            ->getJson(route('companies.invoices.index', [$company->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'company_must_be_issuer',
            ]);
    }

    /**
     * Test user cannot access invoice that doesn't belong to company
     */
    public function test_user_cannot_access_invoice_from_different_company(): void
    {
        $user = User::factory()->create();
        $company1 = $this->createIssuerCompanyWithUser($user);
        $company2 = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $company2->users()->attach($user->id);

        $invoice = Invoice::factory()->create([
            'company_id' => $company2->id,
        ]);

        $token = $this->getAuthToken($user);

        // Try to access invoice from company2 via company1 route
        $response = $this->authenticated($token)
            ->getJson(route('companies.invoices.show', [$company1->id, $invoice->id]));

        $response->assertStatus(404);
    }
}

