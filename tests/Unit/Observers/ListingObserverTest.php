<?php

namespace Tests\Unit\Observers;

use App\Enums\ListingStatus;
use App\Jobs\GenerateAlertsForListing;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ListingObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_alert_generation_when_a_listing_is_created_live(): void
    {
        Queue::fake([GenerateAlertsForListing::class]);

        $listing = Listing::factory()->live()->create();

        Queue::assertPushed(GenerateAlertsForListing::class, fn (GenerateAlertsForListing $job) => $job->listing->is($listing));
    }

    /**
     * Queue::fake() ignores afterCommit, so this runs on the real sync queue
     * and counts jobs started. Alert rows can't show it: the rollback would
     * undo them either way.
     */
    public function test_does_not_run_alert_generation_when_the_publish_is_rolled_back(): void
    {
        $jobsRun = 0;
        Queue::before(function () use (&$jobsRun): void {
            $jobsRun++;
        });

        try {
            DB::transaction(function (): void {
                Listing::factory()->live()->create();

                throw new RuntimeException('Publish failed.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $jobsRun);
    }

    #[DataProvider('nonLiveStates')]
    public function test_does_not_dispatch_when_a_listing_is_created_in_a_non_live_state(string $state): void
    {
        Queue::fake([GenerateAlertsForListing::class]);

        Listing::factory()->{$state}()->create();

        Queue::assertNotPushed(GenerateAlertsForListing::class);
    }

    #[DataProvider('nonLiveStates')]
    public function test_dispatches_when_a_listing_moves_to_live(string $state): void
    {
        $listing = Listing::factory()->{$state}()->create();
        Queue::fake([GenerateAlertsForListing::class]);

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        Queue::assertPushed(GenerateAlertsForListing::class, fn (GenerateAlertsForListing $job) => $job->listing->is($listing));
    }

    public function test_does_not_dispatch_when_a_live_listing_changes_without_a_status_change(): void
    {
        $listing = Listing::factory()->live()->create();
        Queue::fake([GenerateAlertsForListing::class]);

        $listing->update(['price' => $listing->price - 10_000]);

        Queue::assertNotPushed(GenerateAlertsForListing::class);
    }

    public function test_does_not_dispatch_when_a_live_listing_leaves_the_market(): void
    {
        $listing = Listing::factory()->live()->create();
        Queue::fake([GenerateAlertsForListing::class]);

        $listing->update(['status' => ListingStatus::Sold]);

        Queue::assertNotPushed(GenerateAlertsForListing::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLiveStates(): array
    {
        return [
            'draft' => ['draft'],
            'under offer' => ['underOffer'],
            'sold' => ['sold'],
        ];
    }
}
