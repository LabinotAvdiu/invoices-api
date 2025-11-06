<?php

namespace Tests\Feature\QuoteLine;

use App\Enums\CompanyType;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteLineDeleteTest extends TestCase
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
     * Test user can delete a quote line
     */
    public function test_user_can_delete_quote_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-DELETE-001',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line->calculateTotals();
        $line->save();

        // Update quote totals
        $quote->calculateTotals();
        $quote->save();

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('quote_lines', [
            'id' => $line->id,
        ]);

        // Verify quote totals are reset to 0
        $quote->refresh();
        $this->assertEquals(0.00, $quote->total_ht);
        $this->assertEquals(0.00, $quote->total_tva);
        $this->assertEquals(0.00, $quote->total_ttc);
    }

    /**
     * Test quote totals are recalculated when deleting a line
     */
    public function test_quote_totals_are_recalculated_when_deleting_line(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-DELETE-002',
        ]);

        $line1 = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'tva_rate' => 20,
        ]);
        $line1->calculateTotals();
        $line1->save();

        $line2 = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'quantity' => 3,
            'unit_price' => 200.00,
            'tva_rate' => 10,
        ]);
        $line2->calculateTotals();
        $line2->save();

        // Initial totals: Line 1: 500 HT + 100 TVA, Line 2: 600 HT + 60 TVA = 1100 HT + 160 TVA
        $quote->calculateTotals();
        $quote->save();

        // Delete line 1
        $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line1->id]))
            ->assertStatus(204);

        // Remaining totals: Line 2: 600 HT + 60 TVA
        $quote->refresh();
        $this->assertEquals(600.00, $quote->total_ht);
        $this->assertEquals(60.00, $quote->total_tva);
        $this->assertEquals(660.00, $quote->total_ttc);
    }

    /**
     * Test user can delete a line from draft quote
     */
    public function test_user_can_delete_line_from_draft_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-LINE-DELETE-003',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('quote_lines', [
            'id' => $line->id,
        ]);
    }

    /**
     * Test user can delete a line from sent quote
     */
    public function test_user_can_delete_line_from_sent_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::SENT->value,
            'number' => 'D-2025-LINE-DELETE-004',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(204);

        // Verify soft delete (deleted_at is set)
        $this->assertSoftDeleted('quote_lines', [
            'id' => $line->id,
        ]);
    }

    /**
     * Test user cannot delete a line from accepted quote
     */
    public function test_user_cannot_delete_line_from_accepted_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::ACCEPTED->value,
            'number' => 'D-2025-LINE-DELETE-005',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);
    }

    /**
     * Test user cannot delete a line from rejected quote
     */
    public function test_user_cannot_delete_line_from_rejected_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::REJECTED->value,
            'number' => 'D-2025-LINE-DELETE-006',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);
    }

    /**
     * Test user cannot delete a line from expired quote
     */
    public function test_user_cannot_delete_line_from_expired_quote(): void
    {
        $customer = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'status' => QuoteStatus::EXPIRED->value,
            'number' => 'D-2025-LINE-DELETE-007',
        ]);

        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->authenticated($this->token)
            ->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);
    }

    /**
     * Test quote lines require authentication for deletion
     */
    public function test_quote_lines_require_authentication_for_deletion(): void
    {
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'number' => 'D-2025-LINE-DELETE-008',
        ]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
        ]);

        $response = $this->deleteJson(route('companies.quotes.lines.destroy', [$this->company->id, $quote->id, $line->id]));
        $response->assertStatus(401);
    }
}

