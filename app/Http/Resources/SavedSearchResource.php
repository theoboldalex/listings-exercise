<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'max_price' => $this->max_price,
            'min_bedrooms' => $this->min_bedrooms,
            'property_type' => $this->property_type?->value,
            'property_type_label' => $this->property_type?->label(),
            'region' => $this->region,
        ];
    }
}
