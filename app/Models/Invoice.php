<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            // Generate number automatically if not provided
            if (empty($invoice->number)) {
                $invoice->number = static::generateNumber($invoice->company_id);
            }
        });
    }

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
        'customer_email',
        'customer_phone',
        'number',
        'status',
        'issue_date',
        'due_date',
        'is_locked',
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
        'status' => InvoiceStatus::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'is_locked' => 'boolean',
        'total_ht' => 'decimal:2',
        'total_tva' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'metadata' => 'array', // JSONB sera automatiquement converti en array
    ];

    /**
     * Get the company that issued this invoice (émetteur).
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
     * Scope a query to only include invoices with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param InvoiceStatus $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope a query to only include draft invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('status', InvoiceStatus::DRAFT->value);
    }

    /**
     * Scope a query to only include sent invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSent($query)
    {
        return $query->where('status', InvoiceStatus::SENT->value);
    }

    /**
     * Scope a query to only include paid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', InvoiceStatus::PAID->value);
    }

    /**
     * Scope a query to only include canceled invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', InvoiceStatus::CANCELED->value);
    }

    /**
     * Scope a query to only include locked invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Scope a query to only include unlocked invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Check if the invoice is overdue based on due_date.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->isPast() 
            && $this->status !== InvoiceStatus::PAID 
            && $this->status !== InvoiceStatus::CANCELED;
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

    /**
     * Lock the invoice (prevent modifications).
     *
     * @return void
     */
    public function lock(): void
    {
        $this->update(['is_locked' => true]);
    }

    /**
     * Unlock the invoice (allow modifications).
     *
     * @return void
     */
    public function unlock(): void
    {
        $this->update(['is_locked' => false]);
    }

    /**
     * Generate a unique invoice number for the given company.
     * Format: F-YYYY-NNNN (e.g., F-2025-0143)
     *
     * @param int $companyId
     * @return string
     */
    public static function generateNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "F-{$year}-";

        // Find all invoice numbers for this company and year
        $invoices = static::where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->pluck('number')
            ->toArray();

        $maxNumber = 0;
        foreach ($invoices as $invoiceNumber) {
            // Extract the number part (after F-YYYY-)
            if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $invoiceNumber, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

