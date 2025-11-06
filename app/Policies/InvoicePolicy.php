<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
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
    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Only allowed if invoice is not locked and status is draft.
     */
    public function update(User $user, Invoice $invoice): Response
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
     * Determine whether the user can delete the model.
     * Only allowed if invoice is not locked and status is draft.
     */
    public function delete(User $user, Invoice $invoice): Response
    {
        if ($invoice->is_locked) {
            return Response::deny('invoice_locked');
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return Response::deny('invoice_can_only_be_deleted_in_draft');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}

