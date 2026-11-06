<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo user that the auth stub resolves every API request as.
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@street.example',
        ]);

        $branches = Branch::factory(8)->create();

        foreach ($branches as $branch) {
            Listing::factory(rand(18, 30))->live()->for($branch)->create();
            Listing::factory(rand(3, 6))->underOffer()->for($branch)->create();
            Listing::factory(rand(4, 8))->sold()->for($branch)->create();
            Listing::factory(rand(2, 5))->for($branch)->create(); // drafts
        }

        $savedSearch = SavedSearch::factory()->for($demoUser)->create([
            'region' => $branches->first()->region,
        ]);

        // The listings went live before this search existed, so nothing alerted;
        // seed two so /alerts isn't empty in the demo.
        Listing::query()->live()->whereBelongsTo($branches->first())->latest('listed_at')->take(2)->get()
            ->each(fn (Listing $listing) => Alert::factory()->for($demoUser)->for($listing)->for($savedSearch)->create());
    }
}
