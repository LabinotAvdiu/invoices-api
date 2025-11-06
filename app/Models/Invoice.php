<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        static::updating(function (Invoice $invoice) {
            // When status changes from draft to sent, create a snapshot and lock the invoice
            if ($invoice->isDirty('status')) {
                $oldStatusRaw = $invoice->getOriginal('status');
                $newStatusRaw = $invoice->status;

                // Convert to string values for comparison
                $oldStatusValue = $oldStatusRaw instanceof InvoiceStatus 
                    ? $oldStatusRaw->value 
                    : ($oldStatusRaw ?? InvoiceStatus::DRAFT->value);
                
                $newStatusValue = $newStatusRaw instanceof InvoiceStatus 
                    ? $newStatusRaw->value 
                    : ($newStatusRaw ?? InvoiceStatus::DRAFT->value);

                // If transitioning from draft to sent
                if ($oldStatusValue === InvoiceStatus::DRAFT->value && $newStatusValue === InvoiceStatus::SENT->value) {
                    // Get the current invoice from database (before update) to create snapshot
                    $currentInvoice = static::with('lines')->find($invoice->id);
                    
                    if ($currentInvoice) {
                        // Create snapshot with current state (still draft)
                        $currentInvoice->createSnapshot();
                    }
                    
                    // Lock the invoice
                    $invoice->is_locked = true;
                }
            }
        });
    }

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => InvoiceStatus::DRAFT,
    ];

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
     * Get the invoice lines for this invoice.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Get the invoice versions (snapshots).
     */
    public function versions(): HasMany
    {
        return $this->hasMany(InvoiceVersion::class);
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
     * Calculate totals from all invoice lines.
     * Sums up total_ht, total_tax (as total_tva), and total_ttc from all lines.
     * Always reloads lines from database to ensure accuracy.
     *
     * @return void
     */
    public function calculateTotals(): void
    {
        // Always reload lines from database to ensure we have the latest data
        $lines = $this->lines()->get();
        
        // Sum all totals from lines
        $this->total_ht = round($lines->sum('total_ht'), 2);
        $this->total_tva = round($lines->sum('total_tax'), 2); // total_tax in lines = total_tva in invoice
        $this->total_ttc = round($lines->sum('total_ttc'), 2);
    }

    /**
     * Create a snapshot of the invoice and its lines when it's sent.
     * This snapshot is stored in invoice_versions table.
     *
     * @return InvoiceVersion
     */
    public function createSnapshot(): InvoiceVersion
    {
        // Load invoice with all relationships
        $this->load(['customer', 'lines']);

        // Prepare snapshot data
        $snapshotData = [
            'invoice' => [
                'id' => $this->id,
                'company_id' => $this->company_id,
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name,
                'customer_address' => $this->customer_address,
                'customer_zip' => $this->customer_zip,
                'customer_city' => $this->customer_city,
                'customer_country' => $this->customer_country,
                'number' => $this->number,
                'status' => $this->status->value,
                'issue_date' => $this->issue_date?->toDateString(),
                'due_date' => $this->due_date?->toDateString(),
                'is_locked' => $this->is_locked,
                'total_ht' => $this->total_ht,
                'total_tva' => $this->total_tva,
                'total_ttc' => $this->total_ttc,
                'metadata' => $this->metadata,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'lines' => $this->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'invoice_id' => $line->invoice_id,
                    'title' => $line->title,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tva_rate' => $line->tva_rate,
                    'total_ht' => $line->total_ht,
                    'total_tax' => $line->total_tax,
                    'total_ttc' => $line->total_ttc,
                    'created_at' => $line->created_at?->toIso8601String(),
                    'updated_at' => $line->updated_at?->toIso8601String(),
                ];
            })->toArray(),
        ];

        // Create the version
        return $this->versions()->create([
            'snapshot_data' => $snapshotData,
            'created_at' => now(),
        ]);
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

