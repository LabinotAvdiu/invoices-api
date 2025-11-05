<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'logo' => $this->whenLoaded('logo', fn () => $this->logo ? new AttachmentResource($this->logo) : null),
            'name' => $this->name,
            'legal_form' => $this->legal_form,
            'siret' => $this->siret,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'creation_date' => $this->creation_date?->toDateString(),
            'sector' => $this->sector,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}


