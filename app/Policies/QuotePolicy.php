<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuotePolicy
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
    public function view(User $user, Quote $quote): bool
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
     * Only allowed if status is draft, sent (via revision), or not accepted/rejected/expired.
     */
    public function update(User $user, Quote $quote): Response
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
     * Determine whether the user can delete the model.
     * Only allowed if status is draft or sent (if not accepted).
     */
    public function delete(User $user, Quote $quote): Response
    {
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
    public function restore(User $user, Quote $quote): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quote $quote): bool
    {
        return false;
    }
}
