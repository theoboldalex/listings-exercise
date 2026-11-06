<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Acceptance tests for the whole flow: save a search over HTTP, a listing goes
 * live, and the alert appears on the alerts page. The queue runs in sync.
 */
class SavedSearchAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_i_am_alerted_when_a_listing_matching_my_search_goes_live(): void
    {
        User::factory()->create();
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $manchester = Branch::factory()->create(['region' => 'Manchester']);
        $this->post('/saved-searches', ['region' => 'Leeds', 'max_price' => 300000]);
        $match = Listing::factory()->draft()->for($leeds)->create(['price' => 250_000]);
        $wrongRegion = Listing::factory()->draft()->for($manchester)->create(['price' => 250_000]);
        $tooExpensive = Listing::factory()->draft()->for($leeds)->create(['price' => 350_000]);

        foreach ([$match, $wrongRegion, $tooExpensive] as $listing) {
            $this->goLive($listing);
        }

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing.id', $match->id)
            );
    }

    public function test_listings_already_live_when_i_save_a_search_do_not_alert(): void
    {
        User::factory()->create();
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        Listing::factory()->live()->for($leeds)->create();

        $this->post('/saved-searches', ['region' => 'Leeds']);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 0));
    }

    public function test_a_listing_that_comes_back_on_the_market_does_not_alert_me_twice(): void
    {
        User::factory()->create();
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $this->post('/saved-searches', ['region' => 'Leeds']);
        $listing = Listing::factory()->draft()->for($leeds)->create();
        $this->goLive($listing);
        $listing->update(['status' => ListingStatus::UnderOffer]);

        $this->goLive($listing);

        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_deleting_a_search_keeps_the_alerts_it_produced(): void
    {
        $me = User::factory()->create();
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $this->post('/saved-searches', ['region' => 'Leeds']);
        $this->goLive(Listing::factory()->draft()->for($leeds)->create());
        $savedSearch = SavedSearch::query()->whereBelongsTo($me)->sole();

        $this->delete("/saved-searches/{$savedSearch->id}");

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 1));
    }

    private function goLive(Listing $listing): void
    {
        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);
    }
}
