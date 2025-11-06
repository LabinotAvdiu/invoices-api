<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteLineRequest;
use App\Http\Requests\UpdateQuoteLineRequest;
use App\Http\Resources\QuoteLineResource;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class QuoteLineController extends Controller
{
    /**
     * Display a listing of the quote lines for a specific quote.
     */
    public function index(Request $request, Company $company, Quote $quote): AnonymousResourceCollection
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        
        $lines = $quote->lines()->latest()->get();

        return QuoteLineResource::collection($lines);
    }

    /**
     * Store a newly created quote line in storage.
     */
    public function store(StoreQuoteLineRequest $request, Company $company, Quote $quote): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        // Le statut du quote est vérifié par la policy
        
        // Check if quote line can be created using policy
        // We use QuoteLine::class to tell Laravel to use QuoteLinePolicy (not QuotePolicy)
        // and pass $quote as the second parameter to the create() method
        $this->authorize('create', [QuoteLine::class, $quote]);

        $validated = $request->validated();
        
        // Force quote_id from route parameter (ignore any quote_id in request)
        $validated['quote_id'] = $quote->id;
        
        // Create quote line instance to use calculateTotals method
        $quoteLine = new QuoteLine($validated);
        
        // Calculate totals using the model method (will override any provided totals)
        $quoteLine->calculateTotals();
        
        // Save the quote line
        $quoteLine->save();
        
        // Recalculate quote totals from all lines
        $quote->calculateTotals();
        $quote->save();

        return QuoteLineResource::make($quoteLine)->response()->setStatusCode(201);
    }

    /**
     * Display the specified quote line.
     */
    public function show(Request $request, Company $company, Quote $quote, QuoteLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        // Le scoping vérifie automatiquement que la line appartient au quote
        
        return QuoteLineResource::make($line)->response();
    }

    /**
     * Update the specified quote line in storage.
     * Only allowed if quote status is draft, sent (via revision), or not accepted/rejected/expired.
     */
    public function update(UpdateQuoteLineRequest $request, Company $company, Quote $quote, QuoteLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        // Le scoping vérifie automatiquement que la line appartient au quote
        // Le statut du quote est vérifié par la policy
        
        // Check if quote line can be updated using policy
        $this->authorize('update', $line);

        $validated = $request->validated();
        
        // Update the line with validated data
        $line->fill($validated);
        
        // Recalculate totals using the model method (will override any provided totals)
        $line->calculateTotals();
        
        // Save the line
        $line->save();
        
        // Recalculate quote totals from all lines
        $quote->calculateTotals();
        $quote->save();

        return QuoteLineResource::make($line)->response();
    }

    /**
     * Remove the specified quote line from storage.
     * Only allowed if quote status is draft or sent (if not accepted).
     */
    public function destroy(Request $request, Company $company, Quote $quote, QuoteLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que le quote appartient à la company
        // Le scoping vérifie automatiquement que la line appartient au quote
        // Le statut du quote est vérifié par la policy
        
        // Check if quote line can be deleted using policy
        $this->authorize('delete', $line);

        $line->delete();
        
        // Recalculate quote totals from remaining lines
        $quote->calculateTotals();
        $quote->save();

        return response()->json([], 204);
    }
}
