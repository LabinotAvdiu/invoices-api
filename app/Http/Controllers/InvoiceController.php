<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the invoices for a specific company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        // Get invoices for this company (as issuer) using the relationship
        // L'accès à la company est vérifié par le middleware company.access
        $invoices = $company->invoices()
            ->with(['customer'])
            ->latest()
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(StoreInvoiceRequest $request, Company $company): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        $validated = $request->validated();
        
        // Force company_id from route parameter (ignore any company_id in request)
        $validated['company_id'] = $company->id;

        $invoice = Invoice::create($validated);
        $invoice->load(['customer']);

        return InvoiceResource::make($invoice)->response()->setStatusCode(201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, Company $company, Invoice $invoice): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        $invoice->load(['customer']);

        return InvoiceResource::make($invoice)->response();
    }

    /**
     * Update the specified invoice in storage.
     * Only allowed if status is draft and invoice is not locked.
     */
    public function update(UpdateInvoiceRequest $request, Company $company, Invoice $invoice): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        
        // Check if invoice can be updated using policy
        $this->authorize('update', $invoice);

        $validated = $request->validated();
        $invoice->update($validated);
        $invoice->load(['customer']);

        return InvoiceResource::make($invoice)->response();
    }

    /**
     * Remove the specified invoice from storage.
     * Only allowed if status is draft and invoice is not locked.
     */
    public function destroy(Request $request, Company $company, Invoice $invoice): JsonResponse
    {
        // L'accès à la company est vérifié par le middleware company.access
        // Le scoping vérifie automatiquement que l'invoice appartient à la company
        
        // Check if invoice can be deleted using policy
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json([], 204);
    }
}

