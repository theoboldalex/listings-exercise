<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The auth stub acts as the first user, so each test creates "me" first.
 */
class SavedSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_only_my_saved_searches(): void
    {
        $me = User::factory()->create();
        $mine = SavedSearch::factory()->for($me)->create(['property_type' => PropertyType::Flat]);
        SavedSearch::factory()->create();

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 1)
                ->where('savedSearches.0.id', $mine->id)
                ->where('savedSearches.0.property_type_label', 'Flat')
            );
    }

    public function test_store_saves_the_search_for_me_and_redirects_to_the_index(): void
    {
        $me = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $this->post('/saved-searches', [
            'property_type' => 'flat',
            'max_price' => 300000,
            'min_bedrooms' => 2,
            'region' => 'Leeds',
        ])->assertRedirect('/saved-searches');

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $me->id,
            'property_type' => 'flat',
            'max_price' => 300000,
            'min_bedrooms' => 2,
            'region' => 'Leeds',
        ]);
    }

    public function test_store_keeps_one_search_when_the_same_criteria_are_saved_twice(): void
    {
        User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $this->post('/saved-searches', ['region' => 'Leeds', 'max_price' => 300000]);
        $this->post('/saved-searches', ['region' => 'Leeds', 'max_price' => 300000]);

        $this->assertDatabaseCount('saved_searches', 1);
    }

    public function test_store_does_not_treat_a_narrower_existing_search_as_a_duplicate(): void
    {
        $me = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($me)->create(['region' => 'Leeds', 'max_price' => null, 'min_bedrooms' => 3]);

        $this->post('/saved-searches', ['region' => 'Leeds']);

        $this->assertDatabaseHas('saved_searches', ['user_id' => $me->id, 'region' => 'Leeds', 'min_bedrooms' => null]);
    }

    public function test_store_rejects_a_search_with_no_criteria(): void
    {
        User::factory()->create();

        $this->post('/saved-searches', ['max_price' => '', 'region' => ''])
            ->assertSessionHasErrors([
                'criteria' => 'Choose at least one criterion, or you would be alerted about every new listing.',
            ]);

        $this->assertDatabaseCount('saved_searches', 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidCriteria')]
    public function test_store_rejects_invalid_criteria(array $payload, string $field): void
    {
        User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $this->post('/saved-searches', $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('saved_searches', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCriteria(): array
    {
        return [
            'region no branch covers' => [['region' => 'Atlantis'], 'region'],
            'unknown property type' => [['property_type' => 'castle'], 'property_type'],
            'zero max price, which matches nothing' => [['max_price' => 0], 'max_price'],
            'zero min bedrooms, which matches everything' => [['min_bedrooms' => 0], 'min_bedrooms'],
            'too many bedrooms' => [['min_bedrooms' => 21], 'min_bedrooms'],
        ];
    }

    public function test_destroy_deletes_my_search(): void
    {
        $me = User::factory()->create();
        $savedSearch = SavedSearch::factory()->for($me)->create();

        $this->delete("/saved-searches/{$savedSearch->id}")->assertRedirect('/saved-searches');

        $this->assertModelMissing($savedSearch);
    }

    public function test_destroy_returns_404_for_someone_elses_search(): void
    {
        User::factory()->create();
        $theirs = SavedSearch::factory()->create();

        $this->delete("/saved-searches/{$theirs->id}")->assertNotFound();

        $this->assertModelExists($theirs);
    }
}
