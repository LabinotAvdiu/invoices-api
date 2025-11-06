<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Relations
            'company_id' => $this->company_id,
            'customer' => $this->whenLoaded('customer', fn () => new CompanyResource($this->customer)),
            'customer_id' => $this->customer_id,
            
            // Informations client (si non enregistré)
            'customer_name' => $this->customer_name,
            'customer_address' => $this->customer_address,
            'customer_zip' => $this->customer_zip,
            'customer_city' => $this->customer_city,
            'customer_country' => $this->customer_country,
            'customer_display_name' => $this->customer_display_name,
            
            // Informations de la facture
            'number' => $this->number,
            'status' => $this->status?->value,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_locked' => $this->is_locked,
            'is_overdue' => $this->isOverdue(),
            
            // Totaux
            'total_ht' => $this->total_ht,
            'total_tva' => $this->total_tva,
            'total_ttc' => $this->total_ttc,
            
            // Métadonnées
            'metadata' => $this->metadata,
                        
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

