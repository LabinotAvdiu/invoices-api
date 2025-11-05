<?php

namespace App\Http\Controllers;

use App\Enums\CompanyType;
use App\Enums\ResponseCode;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Display a listing of the companies.
     */
    public function index(): AnonymousResourceCollection
    {
        $companies = Company::with('logo')->latest()->paginate(15);

        return CompanyResource::collection($companies);
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Remove logo from validated data as it's handled separately
        $logoFile = $request->file('logo');
        unset($validated['logo']);

        $company = Company::create($validated);

        // If customer type, attach to the authenticated user
        if ($company->type === CompanyType::CUSTOMER) {
            $company->users()->attach($request->user()->id);
        }

        // Handle logo upload if provided
        if ($logoFile) {
            $this->uploadLogo($company, $logoFile);
        }

        $company->load('logo');

        return response()->json([
            'code' => ResponseCode::COMPANY_CREATED,
            'company' => new CompanyResource($company),
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): JsonResponse
    {
        $company->load('logo');

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Update the specified company in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

        // Remove logo from validated data as it's handled separately
        $logoFile = $request->file('logo');
        unset($validated['logo']);

        $company->update($validated);

        // Handle logo upload if provided
        if ($logoFile) {
            $this->uploadLogo($company, $logoFile);
        }

        $company->load('logo');

        return response()->json([
            'code' => ResponseCode::COMPANY_UPDATED,
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Upload and store company logo.
     */
    private function uploadLogo(Company $company, $logoFile): void
    {
        // Generate unique filename
        $extension = $logoFile->getClientOriginalExtension();
        $filename = 'company-' . $company->id . '-' . time() . '.' . $extension;
        $path = 'logos/' . $filename;

        // Store the file
        $storedPath = $logoFile->storeAs('logos', $filename, 'public');

        // Prepare attachment data
        $logoData = [
            'name' => $logoFile->getClientOriginalName(),
            'type' => $logoFile->getMimeType(),
            'size' => $logoFile->getSize(),
            'path' => Storage::disk('public')->url($storedPath),
            'extension' => $extension,
        ];

        // Create or update logo attachment
        $company->setLogo($logoData);
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy(Company $company): JsonResponse
    {
        // Logo deletion is handled automatically in the Company model's deleting event
        $company->delete();

        return response()->json([
            'code' => ResponseCode::COMPANY_DELETED,
            'message' => 'Company deleted successfully',
        ]);
    }
}


