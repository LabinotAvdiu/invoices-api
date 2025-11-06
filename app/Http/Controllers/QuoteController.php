<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Company;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    /**
     * Display a listing of the quotes for a specific company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        // Get quotes for this company (as issuer) using the relationship
        // L'accès à la company est vérifié par le middleware company.access
        $quotes = $company->quotes()
            ->with(['customer'])
            ->latest()
            ->paginate(15);

        return QuoteResource::collection($quotes);
    }

    /**
     * Store a newly created quote in storage.
     */
    public function store(StoreQuoteRequest $request, Company $company): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        $validated = $request->validated();
        
        // Force company_id from route parameter (ignore any company_id in request)
        $validated['company_id'] = $company->id;
        
        // Set default status to draft if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = QuoteStatus::DRAFT;
        }

        $quote = Quote::create($validated);
        $quote->load(['customer']);

        return QuoteResource::make($quote)->response()->setStatusCode(201);
    }

    /**
     * Display the specified quote.
     */
    public function show(Request $request, Company $company, Quote $quote): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        $quote->load(['customer']);

        return QuoteResource::make($quote)->response();
    }

    /**
     * Update the specified quote in storage.
     * Only allowed if status is draft, sent (via revision), or not accepted/rejected/expired.
     */
    public function update(UpdateQuoteRequest $request, Company $company, Quote $quote): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        
        // Check if quote can be updated using policy
        $this->authorize('update', $quote);

        $validated = $request->validated();
        $quote->update($validated);
        $quote->load(['customer']);

        return QuoteResource::make($quote)->response();
    }

    /**
     * Remove the specified quote from storage.
     * Only allowed if status is draft or revision (sent can be deleted if not accepted).
     */
    public function destroy(Request $request, Company $company, Quote $quote): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        
        // Check if quote can be deleted using policy
        $this->authorize('delete', $quote);

        $quote->delete();

        return response()->json([], 204);
    }
}
