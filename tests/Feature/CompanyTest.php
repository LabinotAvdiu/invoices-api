<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get authenticated user token for testing
     */
    private function getAuthToken(): string
    {
        $user = User::factory()->create();
        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test user can list companies
     */
    public function test_user_can_list_companies(): void
    {
        Company::factory()->count(5)->create();
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/companies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'logo',
                        'name',
                        'legal_form',
                        'siret',
                        'address',
                        'zip_code',
                        'city',
                        'country',
                        'phone',
                        'email',
                        'creation_date',
                        'sector',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * Test user can create a company with required fields only
     */
    public function test_user_can_create_company_with_required_fields(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'logo',
                ],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Test Company',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Test Company',
        ]);
    }

    /**
     * Test user can create a company with all fields
     */
    public function test_user_can_create_company_with_all_fields(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Complete Company',
                'legal_form' => 'SARL',
                'siret' => '12345678901234',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'country' => 'France',
                'phone' => '+33612345678',
                'email' => 'contact@company.com',
                'creation_date' => '2020-01-01',
                'sector' => 'Technology',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Complete Company',
                    'legal_form' => 'SARL',
                    'siret' => '12345678901234',
                    'city' => 'Paris',
                    'country' => 'France',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Complete Company',
            'siret' => '12345678901234',
        ]);
    }

    /**
     * Test user can create a company with logo
     */
    public function test_user_can_create_company_with_logo(): void
    {
        Storage::fake('public');
        $token = $this->getAuthToken();

        $logo = UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Company with Logo',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'logo' => $logo,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'logo' => [
                        'id',
                        'name',
                        'type',
                        'size',
                        'path',
                        'extension',
                    ],
                ],
            ]);

        $company = Company::where('name', 'Company with Logo')->first();
        $company->load('logo');
        $this->assertNotNull($company->logo);
        $this->assertEquals('image/jpeg', $company->logo->type);
    }

    /**
     * Test user can view a specific company
     */
    public function test_user_can_view_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Viewable Company',
        ]);
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $company->id,
                    'name' => 'Viewable Company',
                ],
            ]);
    }

    /**
     * Test user can update a company
     */
    public function test_user_can_update_company(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'Original Name',
            'city' => 'Lyon',
        ]);
        $company->users()->attach($user->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Updated Name',
                'address' => '456 New Street',
                'zip_code' => '69001',
                'city' => 'Paris',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $company->id,
                    'name' => 'Updated Name',
                    'city' => 'Paris',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Name',
            'city' => 'Paris',
        ]);
    }

    /**
     * Test user can update company logo
     */
    public function test_user_can_update_company_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $company->users()->attach($user->id);

        // Create initial logo
        $oldLogo = UploadedFile::fake()->create('old-logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
                'address' => $company->address,
                'zip_code' => $company->zip_code,
                'city' => $company->city,
                'logo' => $oldLogo,
            ]);

        $company->refresh();
        $company->load('logo');
        $oldLogoPath = $company->logo->path;

        // Update with new logo
        $newLogo = UploadedFile::fake()->create('new-logo.png', 100, 'image/png');
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
                'address' => $company->address,
                'zip_code' => $company->zip_code,
                'city' => $company->city,
                'logo' => $newLogo,
            ]);

        $response->assertStatus(200);

        $company->refresh();
        $company->load('logo');
        $this->assertNotNull($company->logo);
        $this->assertNotEquals($oldLogoPath, $company->logo->path);
        $this->assertStringContainsString('new-logo', $company->logo->name);
    }

    /**
     * Test old logo is deleted when new logo is uploaded
     */
    public function test_old_logo_is_deleted_when_new_logo_uploaded(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $company->users()->attach($user->id);

        // Create initial logo
        $oldLogo = UploadedFile::fake()->create('old-logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
                'address' => $company->address,
                'zip_code' => $company->zip_code,
                'city' => $company->city,
                'logo' => $oldLogo,
            ]);

        $company->refresh();
        $company->load('logo');
        $oldLogoId = $company->logo->id;

        // Update with new logo
        $newLogo = UploadedFile::fake()->create('new-logo.png', 100, 'image/png');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
                'address' => $company->address,
                'zip_code' => $company->zip_code,
                'city' => $company->city,
                'logo' => $newLogo,
            ]);

        // Old logo should be deleted from database
        $this->assertDatabaseMissing('attachments', [
            'id' => $oldLogoId,
        ]);

        // Only one logo should exist
        $company->refresh();
        $company->load('logo');
        $this->assertNotNull($company->logo);
        $this->assertEquals(1, Attachment::where('model_type', Company::class)
            ->where('model_id', $company->id)
            ->count());
    }

    /**
     * Test user can delete a company
     */
    public function test_user_can_delete_company(): void
    {
        $company = Company::factory()->create();
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);
    }

    /**
     * Test company deletion deletes associated logo
     */
    public function test_company_deletion_deletes_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $company = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);
        $company->users()->attach($user->id);

        // Create logo
        $logo = UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
                'address' => $company->address,
                'zip_code' => $company->zip_code,
                'city' => $company->city,
                'logo' => $logo,
            ]);

        $company->refresh();
        $company->load('logo');
        $logoId = $company->logo->id;

        // Delete company
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/companies/{$company->id}");

        // Logo should be deleted
        $this->assertDatabaseMissing('attachments', [
            'id' => $logoId,
        ]);
    }

    /**
     * Test creation requires name
     */
    public function test_creation_requires_name(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test creation requires unique name
     */
    public function test_creation_requires_unique_name(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Create a company attached to this user
        $existingCompany = Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'Existing Company',
        ]);
        $existingCompany->users()->attach($user->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Existing Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test logo must be an image
     */
    public function test_logo_must_be_an_image(): void
    {
        $token = $this->getAuthToken();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'logo' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    /**
     * Test logo size validation
     */
    public function test_logo_size_validation(): void
    {
        $token = $this->getAuthToken();
        // Create a file larger than 2MB
        $largeFile = UploadedFile::fake()->create('large.jpg', 3000, 'image/jpeg'); // 3MB

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'logo' => $largeFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    /**
     * Test SIRET validation
     */
    public function test_siret_validation(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'siret' => '123', // Invalid: must be 14 digits
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['siret']);
    }

    /**
     * Test legal form validation
     */
    public function test_legal_form_validation(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'legal_form' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['legal_form']);
    }

    /**
     * Test email validation
     */
    public function test_email_validation(): void
    {
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'type' => 'customer',
                'name' => 'Test Company',
                'address' => '123 Main Street',
                'zip_code' => '75001',
                'city' => 'Paris',
                'email' => 'invalid-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test update requires authentication
     */
    public function test_companies_require_authentication(): void
    {
        $response = $this->getJson('/api/companies');
        $response->assertStatus(401);

        $response = $this->postJson('/api/companies', ['name' => 'Test']);
        $response->assertStatus(401);

        $company = Company::factory()->create();
        $response = $this->getJson("/api/companies/{$company->id}");
        $response->assertStatus(401);

        $response = $this->putJson("/api/companies/{$company->id}", ['name' => 'Updated']);
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/companies/{$company->id}");
        $response->assertStatus(401);
    }

    /**
     * Test company can have multiple users
     */
    public function test_company_can_have_multiple_users(): void
    {
        $company = Company::factory()->create();
        $users = User::factory()->count(3)->create();

        $company->users()->attach($users->pluck('id'));

        $this->assertEquals(3, $company->users()->count());
        $this->assertTrue($company->users->contains($users->first()));
    }

    /**
     * Test user can belong to multiple companies
     */
    public function test_user_can_belong_to_multiple_companies(): void
    {
        $user = User::factory()->create();
        $companies = Company::factory()->count(3)->create();

        $user->companies()->attach($companies->pluck('id'));

        $this->assertEquals(3, $user->companies()->count());
        $this->assertTrue($user->companies->contains($companies->first()));
    }

    /**
     * Test issuer scope filters only issuer companies
     */
    public function test_issuer_scope_filters_issuer_companies(): void
    {
        // Create 3 issuer companies
        Company::factory()->count(3)->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        // Create 2 customer companies
        Company::factory()->count(2)->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $issuerCompanies = Company::issuer()->get();

        $this->assertEquals(3, $issuerCompanies->count());
        $issuerCompanies->each(function ($company) {
            $this->assertEquals(CompanyType::ISSUER->value, $company->type->value);
        });
    }

    /**
     * Test customer scope filters only customer companies
     */
    public function test_customer_scope_filters_customer_companies(): void
    {
        // Create 2 issuer companies
        Company::factory()->count(2)->create([
            'type' => CompanyType::ISSUER->value,
        ]);

        // Create 4 customer companies
        Company::factory()->count(4)->create([
            'type' => CompanyType::CUSTOMER->value,
        ]);

        $customerCompanies = Company::customer()->get();

        $this->assertEquals(4, $customerCompanies->count());
        $customerCompanies->each(function ($company) {
            $this->assertEquals(CompanyType::CUSTOMER->value, $company->type->value);
        });
    }

    /**
     * Test issuer scope can be combined with other query methods
     */
    public function test_issuer_scope_can_be_combined_with_other_queries(): void
    {
        Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
            'name' => 'Test Issuer Company',
        ]);

        Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'Test Customer Company',
        ]);

        $issuerCompany = Company::issuer()->where('name', 'Test Issuer Company')->first();

        $this->assertNotNull($issuerCompany);
        $this->assertEquals(CompanyType::ISSUER->value, $issuerCompany->type->value);
        $this->assertEquals('Test Issuer Company', $issuerCompany->name);

        $customerCompany = Company::issuer()->where('name', 'Test Customer Company')->first();
        $this->assertNull($customerCompany);
    }

    /**
     * Test customer scope can be combined with other query methods
     */
    public function test_customer_scope_can_be_combined_with_other_queries(): void
    {
        Company::factory()->create([
            'type' => CompanyType::CUSTOMER->value,
            'name' => 'Test Customer Company',
        ]);

        Company::factory()->create([
            'type' => CompanyType::ISSUER->value,
            'name' => 'Test Issuer Company',
        ]);

        $customerCompany = Company::customer()->where('name', 'Test Customer Company')->first();

        $this->assertNotNull($customerCompany);
        $this->assertEquals(CompanyType::CUSTOMER->value, $customerCompany->type->value);
        $this->assertEquals('Test Customer Company', $customerCompany->name);

        $issuerCompany = Company::customer()->where('name', 'Test Issuer Company')->first();
        $this->assertNull($issuerCompany);
    }
}

