<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same criteria as the listings index filters, so a saved search is always
     * something the user could also search for by hand.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            // min:1, not the index's min:0: "0+ beds" matches every listing and a
            // £0 cap matches none, so neither counts as a real criterion.
            'max_price' => ['nullable', 'integer', 'min:1'],
            'min_bedrooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            // A region no branch covers would silently never match.
            'region' => ['nullable', 'string', 'max:100', Rule::exists('branches', 'region')],
        ];
    }

    /**
     * A search with no criteria would alert on every new listing.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->anyFilled(SavedSearch::CRITERIA)) {
                    $validator->errors()->add('criteria', 'Choose at least one criterion, or you would be alerted about every new listing.');
                }
            },
        ];
    }
}
