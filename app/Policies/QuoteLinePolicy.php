<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuoteLinePolicy
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
    public function view(User $user, QuoteLine $quoteLine): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Check if the quote can be updated (same rules as quote update).
     */
    public function create(User $user, Quote $quote): Response
    {
        $lockedStatuses = [
            QuoteStatus::ACCEPTED,
            QuoteStatus::REJECTED,
            QuoteStatus::EXPIRED,
        ];

        if (in_array($quote->status, $lockedStatuses)) {
            return Response::deny('quote_locked');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     * Check if the quote can be updated (same rules as quote update).
     */
    public function update(User $user, QuoteLine $quoteLine): Response
    {
        $quote = $quoteLine->quote;
        
        $lockedStatuses = [
            QuoteStatus::ACCEPTED,
            QuoteStatus::REJECTED,
            QuoteStatus::EXPIRED,
        ];

        if (in_array($quote->status, $lockedStatuses)) {
            return Response::deny('quote_locked');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     * Check if the quote can be deleted (same rules as quote delete).
     */
    public function delete(User $user, QuoteLine $quoteLine): Response
    {
        $quote = $quoteLine->quote;
        
        $nonDeletableStatuses = [
            QuoteStatus::ACCEPTED,
            QuoteStatus::REJECTED,
            QuoteStatus::EXPIRED,
        ];

        if (in_array($quote->status, $nonDeletableStatuses)) {
            return Response::deny('quote_cannot_be_deleted');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QuoteLine $quoteLine): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QuoteLine $quoteLine): bool
    {
        return false;
    }
}
