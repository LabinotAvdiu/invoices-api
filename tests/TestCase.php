<?php

namespace Tests;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Return a test instance with Authorization header set.
     * This helper method avoids repeating withHeader('Authorization', 'Bearer ' . $token) in every test.
     *
     * @param string|null $token The authentication token. If null, no header will be set.
     * @return $this
     */
    protected function authenticated(string $token = null): self
    {
        if ($token === null) {
            return $this;
        }

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

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

