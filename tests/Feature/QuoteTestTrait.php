<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\User;

trait QuoteTestTrait
{
    /**
     * Get authenticated user token for testing
     */
    protected function getAuthToken(User $user = null): string
    {
        $user = $user ?? User::factory()->create();
        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * Create an issuer company with user access
     */
    protected function createIssuerCompanyWithUser(User $user = null): Company
    {
        $user = $user ?? User::factory()->create();
        $company = Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
        ]);
        $company->users()->attach($user->id);
        return $company;
    }
}

