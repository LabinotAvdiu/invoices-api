<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'customer_name',
        'customer_address',
        'customer_zip',
        'customer_city',
        'customer_country',
        'number',
        'status',
        'issue_date',
        'valid_until',
        'total_ht',
        'total_tva',
        'total_ttc',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => QuoteStatus::class,
        'issue_date' => 'date',
        'valid_until' => 'date',
        'total_ht' => 'decimal:2',
        'total_tva' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'metadata' => 'array', // JSONB sera automatiquement converti en array
    ];

    /**
     * Get the company that issued this quote (émetteur).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the customer company (if registered).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    /**
     * Scope a query to only include quotes with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param QuoteStatus $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, QuoteStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope a query to only include draft quotes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('status', QuoteStatus::DRAFT->value);
    }

    /**
     * Scope a query to only include sent quotes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSent($query)
    {
        return $query->where('status', QuoteStatus::SENT->value);
    }

    /**
     * Scope a query to only include accepted quotes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', QuoteStatus::ACCEPTED->value);
    }

    /**
     * Scope a query to only include rejected quotes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status', QuoteStatus::REJECTED->value);
    }

    /**
     * Scope a query to only include expired quotes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('status', QuoteStatus::EXPIRED->value);
    }

    /**
     * Check if the quote is expired based on valid_until date.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }

        return $this->valid_until->isPast() && $this->status !== QuoteStatus::EXPIRED;
    }

    /**
     * Get the customer name (from company or customer_name field).
     *
     * @return string
     */
    public function getCustomerDisplayNameAttribute(): string
    {
        if ($this->customer_id && $this->customer) {
            return $this->customer->name;
        }

        return $this->customer_name ?? 'Client non enregistré';
    }
}
