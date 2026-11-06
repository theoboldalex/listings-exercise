<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * Matches any factory listing (they top out at £950k), so a test only sets
     * the criteria it is about. Random criteria here made tests flaky.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'max_price' => 1_000_000,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ];
    }
}
