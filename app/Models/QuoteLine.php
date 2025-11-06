<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteLine extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quote_id',
        'title',
        'description',
        'quantity',
        'unit_price',
        'tva_rate',
        'total_ht',
        'total_tax',
        'total_ttc',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tva_rate' => 'decimal:2',
        'total_ht' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    /**
     * Get the quote that owns this line.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Calculate totals based on quantity, unit_price and tva_rate.
     * This method can be called before saving to ensure totals are correct.
     *
     * @return void
     */
    public function calculateTotals(): void
    {
        $this->total_ht = round($this->quantity * $this->unit_price, 2);
        $this->total_tax = round($this->total_ht * ($this->tva_rate / 100), 2);
        $this->total_ttc = round($this->total_ht + $this->total_tax, 2);
    }
}
