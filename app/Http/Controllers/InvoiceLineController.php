<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceLineRequest;
use App\Http\Requests\UpdateInvoiceLineRequest;
use App\Http\Resources\InvoiceLineResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class InvoiceLineController extends Controller
{
    /**
     * Display a listing of the invoice lines for a specific invoice.
     */
    public function index(Request $request, Company $company, Invoice $invoice): AnonymousResourceCollection
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        
        $lines = $invoice->lines()->latest()->get();

        return InvoiceLineResource::collection($lines);
    }

    /**
     * Store a newly created invoice line in storage.
     */
    public function store(StoreInvoiceLineRequest $request, Company $company, Invoice $invoice): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        // Le statut de l'invoice est vérifié par la policy
        
        // Check if invoice line can be created using policy
        // We use InvoiceLine::class to tell Laravel to use InvoiceLinePolicy (not InvoicePolicy)
        // and pass $invoice as the second parameter to the create() method
        $this->authorize('create', [InvoiceLine::class, $invoice]);

        $validated = $request->validated();
        
        // Force invoice_id from route parameter (ignore any invoice_id in request)
        $validated['invoice_id'] = $invoice->id;
        
        // Create invoice line instance to use calculateTotals method
        $invoiceLine = new InvoiceLine($validated);
        
        // Calculate totals using the model method (will override any provided totals)
        $invoiceLine->calculateTotals();
        
        // Save the invoice line
        $invoiceLine->save();
        
        // Recalculate invoice totals from all lines
        $invoice->calculateTotals();
        $invoice->save();

        return InvoiceLineResource::make($invoiceLine)->response()->setStatusCode(201);
    }

    /**
     * Display the specified invoice line.
     */
    public function show(Request $request, Company $company, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        // Le scoping vérifie automatiquement que la line appartient à l'invoice
        
        return InvoiceLineResource::make($line)->response();
    }

    /**
     * Update the specified invoice line in storage.
     * Only allowed if invoice status is draft and invoice is not locked.
     */
    public function update(UpdateInvoiceLineRequest $request, Company $company, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        // Le scoping vérifie automatiquement que la line appartient à l'invoice
        // Le statut de l'invoice est vérifié par la policy
        
        // Check if invoice line can be updated using policy
        $this->authorize('update', $line);

        $validated = $request->validated();
        
        // Update the line with validated data
        $line->fill($validated);
        
        // Recalculate totals using the model method (will override any provided totals)
        $line->calculateTotals();
        
        // Save the line
        $line->save();
        
        // Recalculate invoice totals from all lines
        $invoice->calculateTotals();
        $invoice->save();

        return InvoiceLineResource::make($line)->response();
    }

    /**
     * Remove the specified invoice line from storage.
     * Only allowed if invoice status is draft and invoice is not locked.
     */
    public function destroy(Request $request, Company $company, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        // Le scoping vérifie automatiquement que la line appartient à l'invoice
        // Le statut de l'invoice est vérifié par la policy
        
        // Check if invoice line can be deleted using policy
        $this->authorize('delete', $line);

        $line->delete();
        
        // Recalculate invoice totals from remaining lines
        $invoice->calculateTotals();
        $invoice->save();

        return response()->json([], 204);
    }
}

