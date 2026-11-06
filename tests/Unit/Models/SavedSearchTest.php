<?php

namespace Tests\Unit\Models;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The matching rule is a query scope, so this unit test needs the database.
 */
class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('criteria')]
    public function test_matching_scope_requires_every_set_criterion_to_match(array $criteria, bool $shouldMatch): void
    {
        $listing = Listing::factory()
            ->for(Branch::factory()->create(['region' => 'Leeds']))
            ->create(['price' => 300_000, 'bedrooms' => 3, 'property_type' => PropertyType::Flat]);
        $savedSearch = SavedSearch::factory()->create(['max_price' => null, ...$criteria]);

        $matched = SavedSearch::query()->matching($listing)->whereKey($savedSearch->id)->exists();

        $this->assertSame($shouldMatch, $matched);
    }

    /**
     * Run against a 3-bed flat in Leeds at £300,000.
     *
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function criteria(): array
    {
        return [
            'max price equal to the price' => [['max_price' => 300_000], true],
            'max price just below the price' => [['max_price' => 299_999], false],
            'min bedrooms equal to the bedrooms' => [['min_bedrooms' => 3], true],
            'min bedrooms above the bedrooms' => [['min_bedrooms' => 4], false],
            'same property type' => [['property_type' => PropertyType::Flat], true],
            'different property type' => [['property_type' => PropertyType::Detached], false],
            'same region' => [['region' => 'Leeds'], true],
            'different region' => [['region' => 'Manchester'], false],
            'every criterion matching' => [
                ['max_price' => 350_000, 'min_bedrooms' => 2, 'property_type' => PropertyType::Flat, 'region' => 'Leeds'],
                true,
            ],
            'one criterion failing among matching ones' => [
                ['max_price' => 350_000, 'min_bedrooms' => 2, 'property_type' => PropertyType::Flat, 'region' => 'Manchester'],
                false,
            ],
            'no criteria (why the request requires at least one)' => [[], true],
        ];
    }
}
