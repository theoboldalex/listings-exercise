<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_only_my_alerts_newest_first(): void
    {
        $me = User::factory()->create();
        $older = Alert::factory()->for($me)->create(['created_at' => now()->subDays(2)]);
        $newer = Alert::factory()->for($me)->create(['created_at' => now()->subDay()]);
        Alert::factory()->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
            );
    }

    /**
     * The page links only live listings, because the listing page 404s for
     * the rest.
     */
    public function test_index_keeps_alerts_for_listings_that_have_since_sold(): void
    {
        $me = User::factory()->create();
        Alert::factory()->for($me)->for(Listing::factory()->sold())->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing.status', 'sold')
            );
    }
}
