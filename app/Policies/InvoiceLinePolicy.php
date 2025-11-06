<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoiceLinePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvoiceLine $invoiceLine): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Check if the invoice can be updated (same rules as invoice update).
     */
    public function create(User $user, Invoice $invoice): Response
    {
        if ($invoice->is_locked) {
            return Response::deny('invoice_locked');
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return Response::deny('invoice_locked');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     * Check if the invoice can be updated (same rules as invoice update).
     */
    public function update(User $user, InvoiceLine $invoiceLine): Response
    {
        $invoice = $invoiceLine->invoice;
        
        if ($invoice->is_locked) {
            return Response::deny('invoice_locked');
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return Response::deny('invoice_locked');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     * Check if the invoice can be deleted (same rules as invoice delete).
     */
    public function delete(User $user, InvoiceLine $invoiceLine): Response
    {
        $invoice = $invoiceLine->invoice;
        
        if ($invoice->is_locked) {
            return Response::deny('invoice_locked');
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return Response::deny('invoice_locked');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvoiceLine $invoiceLine): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvoiceLine $invoiceLine): bool
    {
        return false;
    }
}

