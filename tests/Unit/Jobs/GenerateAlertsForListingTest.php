<?php

namespace Tests\Unit\Jobs;

use App\Enums\ListingStatus;
use App\Jobs\GenerateAlertsForListing;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateAlertsForListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_alert_for_each_user_with_a_matching_search(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        SavedSearch::factory()->for($alice)->create(['max_price' => 400_000]);
        SavedSearch::factory()->for($bob)->create(['max_price' => 400_000]);
        SavedSearch::factory()->create(['max_price' => 200_000]);
        $listing = $this->liveListing();

        (new GenerateAlertsForListing($listing))->handle();

        $this->assertDatabaseCount('alerts', 2);
        $this->assertDatabaseHas('alerts', ['user_id' => $alice->id, 'listing_id' => $listing->id]);
        $this->assertDatabaseHas('alerts', ['user_id' => $bob->id, 'listing_id' => $listing->id]);
    }

    public function test_creates_one_alert_when_several_of_a_users_searches_match(): void
    {
        $user = User::factory()->create();
        $first = SavedSearch::factory()->for($user)->create(['max_price' => 400_000]);
        SavedSearch::factory()->for($user)->create(['max_price' => 500_000]);
        $listing = $this->liveListing();

        (new GenerateAlertsForListing($listing))->handle();

        $this->assertDatabaseCount('alerts', 1);
        $this->assertDatabaseHas('alerts', ['user_id' => $user->id, 'saved_search_id' => $first->id]);
    }

    public function test_running_twice_does_not_duplicate_alerts(): void
    {
        SavedSearch::factory()->create(['max_price' => 400_000]);
        $listing = $this->liveListing();

        (new GenerateAlertsForListing($listing))->handle();
        (new GenerateAlertsForListing($listing))->handle();

        $this->assertDatabaseCount('alerts', 1);
    }

    /**
     * 7,000 alerts in one insert is 35,000 bound values, past SQLite's 32,766.
     * Rows are bulk-inserted because factories would make this test slow.
     */
    public function test_alerts_every_matching_user_when_more_match_than_one_insert_can_hold(): void
    {
        $now = now();
        $userIds = range(1, 7_000);
        foreach (array_chunk($userIds, 1_000) as $chunk) {
            DB::table('users')->insert(array_map(fn (int $id) => [
                'id' => $id, 'name' => "User {$id}", 'email' => "user{$id}@example.test", 'password' => 'x',
                'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
            DB::table('saved_searches')->insert(array_map(fn (int $id) => [
                'user_id' => $id, 'max_price' => 400_000, 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }
        $listing = $this->liveListing();

        (new GenerateAlertsForListing($listing))->handle();

        $this->assertDatabaseCount('alerts', 7_000);
    }

    public function test_does_not_alert_when_the_listing_is_no_longer_live_by_the_time_it_runs(): void
    {
        SavedSearch::factory()->create(['max_price' => 400_000]);
        $listing = $this->liveListing();
        $listing->status = ListingStatus::UnderOffer;

        (new GenerateAlertsForListing($listing))->handle();

        $this->assertDatabaseCount('alerts', 0);
    }

    /**
     * Created with model events off, so the observer doesn't run the job
     * before the test does.
     */
    private function liveListing(): Listing
    {
        return Listing::withoutEvents(
            fn () => Listing::factory()->live()->create(['price' => 300_000, 'bedrooms' => 3])
        );
    }
}
