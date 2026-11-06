<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Jobs\GenerateAlertsForListing;
use App\Models\Listing;

/**
 * Alerts fire when a listing goes live, whether it is created live or moves
 * there from another status.
 *
 * Model events don't fire for query-builder writes (`Listing::query()->update()`,
 * `insert()`), so any bulk import must dispatch GenerateAlertsForListing itself.
 */
class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Live) {
            $this->generateAlerts($listing);
        }
    }

    public function updated(Listing $listing): void
    {
        if ($listing->wasChanged('status') && $listing->status === ListingStatus::Live) {
            $this->generateAlerts($listing);
        }
    }

    /**
     * Queued after commit, so a rolled-back publish never alerts anyone and
     * matching can't slow down or fail the write that published the listing.
     */
    private function generateAlerts(Listing $listing): void
    {
        GenerateAlertsForListing::dispatch($listing)->afterCommit();
    }
}
