<?php

namespace Tests\Feature;

use App\Enums\ResponseCode;
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
                'name' => 'Test Company',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'code',
                'company' => [
                    'id',
                    'name',
                    'logo',
                ],
            ])
            ->assertJson([
                'code' => ResponseCode::COMPANY_CREATED,
                'company' => [
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
                'code' => ResponseCode::COMPANY_CREATED,
                'company' => [
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
                'name' => 'Company with Logo',
                'logo' => $logo,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'code',
                'company' => [
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
                'code',
                'company' => [
                    'id',
                    'name',
                ],
            ])
            ->assertJson([
                'code' => ResponseCode::SUCCESS,
                'company' => [
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
        $company = Company::factory()->create([
            'name' => 'Original Name',
            'city' => 'Lyon',
        ]);
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Updated Name',
                'city' => 'Paris',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => ResponseCode::COMPANY_UPDATED,
                'company' => [
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
        $company = Company::factory()->create();
        $token = $this->getAuthToken();

        // Create initial logo
        $oldLogo = UploadedFile::fake()->create('old-logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
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
        $company = Company::factory()->create();
        $token = $this->getAuthToken();

        // Create initial logo
        $oldLogo = UploadedFile::fake()->create('old-logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
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

        $response->assertStatus(200)
            ->assertJson([
                'code' => ResponseCode::COMPANY_DELETED,
                'message' => 'Company deleted successfully',
            ]);

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
        $company = Company::factory()->create();
        $token = $this->getAuthToken();

        // Create logo
        $logo = UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/companies/{$company->id}", [
                'name' => $company->name,
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
        Company::factory()->create(['name' => 'Existing Company']);
        $token = $this->getAuthToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/companies', [
                'name' => 'Existing Company',
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
                'name' => 'Test Company',
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
                'name' => 'Test Company',
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
                'name' => 'Test Company',
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
                'name' => 'Test Company',
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
                'name' => 'Test Company',
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
}

