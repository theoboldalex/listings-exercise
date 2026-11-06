<?php

namespace App\Jobs;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class GenerateAlertsForListing implements ShouldQueue
{
    use Queueable;

    /**
     * A listing deleted before the job runs has nothing to alert about.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Listing $listing) {}

    /**
     * Create one alert per user whose saved searches match the listing. Safe to
     * run more than once: the unique (user_id, listing_id) key ignores repeats.
     */
    public function handle(): void
    {
        // The listing may have been withdrawn between dispatch and now.
        if ($this->listing->status !== ListingStatus::Live) {
            return;
        }

        $now = now();

        // shortcut: loads every matching search into memory (~1.4 KB each, so a
        // 128 MB worker runs out near 90k matches for one listing); past ~50k,
        // switch to insertOrIgnoreUsing() with GROUP BY user_id.
        SavedSearch::query()
            ->matching($this->listing)
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->unique('user_id')
            ->map(fn (SavedSearch $savedSearch) => [
                'user_id' => $savedSearch->user_id,
                'listing_id' => $this->listing->id,
                'saved_search_id' => $savedSearch->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            // One insert per 1,000 rows: a single statement for ~6,500+ users
            // exceeds SQLite's limit on bound values (~13,000 on MySQL/Postgres).
            ->chunk(1000)
            ->each(fn (Collection $rows) => Alert::query()->insertOrIgnore($rows->values()->all()));
    }
}
