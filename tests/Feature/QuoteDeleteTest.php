<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteDeleteTest extends TestCase
{
    use RefreshDatabase;
    use QuoteTestTrait;

    /**
     * Test user can delete a draft quote
     */
    public function test_user_can_delete_draft_quote(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'status' => QuoteStatus::DRAFT->value,
            'number' => 'D-2025-TEST-DELETE-1',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('quotes', [
            'id' => $quote->id,
        ]);
    }

    /**
     * Test user can delete a sent quote
     */
    public function test_user_can_delete_sent_quote(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'status' => QuoteStatus::SENT->value,
            'number' => 'D-2025-TEST-DELETE-2',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('quotes', [
            'id' => $quote->id,
        ]);
    }

    /**
     * Test user cannot delete an accepted quote
     */
    public function test_user_cannot_delete_accepted_quote(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'status' => QuoteStatus::ACCEPTED->value,
            'number' => 'D-2025-TEST-DELETE-3',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
        ]);
    }

    /**
     * Test user cannot delete a rejected quote
     */
    public function test_user_cannot_delete_rejected_quote(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'status' => QuoteStatus::REJECTED->value,
            'number' => 'D-2025-TEST-DELETE-4',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
        ]);
    }

    /**
     * Test user cannot delete an expired quote
     */
    public function test_user_cannot_delete_expired_quote(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $token = $this->getAuthToken($user);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'status' => QuoteStatus::EXPIRED->value,
            'number' => 'D-2025-TEST-DELETE-5',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'quote_cannot_be_deleted',
            ]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
        ]);
    }

    /**
     * Test quotes require authentication for deletion
     */
    public function test_quotes_require_authentication_for_deletion(): void
    {
        $user = User::factory()->create();
        $company = $this->createIssuerCompanyWithUser($user);
        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'number' => 'D-2025-TEST-AUTH-DELETE',
        ]);

        $response = $this->deleteJson("/api/companies/{$company->id}/quotes/{$quote->id}");
        $response->assertStatus(401);
    }
}

