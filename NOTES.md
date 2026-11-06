# Saved-search alerts — notes

To try it, run `php artisan migrate:fresh --seed` and `composer run dev`. The demo user has one saved search and two alerts. There's no publish UI, so to see a new alert, publish a matching draft and refresh `/alerts`:

```bash
QUEUE_CONNECTION=sync php artisan tinker --execute '$s = App\Models\SavedSearch::first(); App\Models\Listing::where("status", "draft")->whereHas("branch", fn ($q) => $q->where("region", $s->region))->firstOrFail()->update(["status" => "live", "listed_at" => now()]);'
```

## Key decisions and trade-offs
- **Trigger: a model observer.** An alert is generated when a listing is created live or changes to live, so any future way of publishing gets alerts without extra work. *Trade-off:* bulk query-builder writes skip model events, so they don't alert. An explicit publish action would be easier to see, but also easy to bypass.
- **A queued job, dispatched after commit.** A rolled-back publish alerts nobody, and matching can't slow down or fail the write. *Trade-off:* alerts need a running worker.
- **Matching in SQL, with each criterion as a column.** One query finds every saved search a listing satisfies, and an empty criterion means "any". *Trade-off:* the matching rule now exists twice, in the listings filters and in the reverse lookup, so a test checks they agree.
- **No spam.**
  - A search needs at least one criterion and a region that exists.
  - A unique key allows one alert per user per listing, so retries and relistings never duplicate.
  - *Trade-off:* an alert records only the first search that matched, and a relisted property doesn't alert people who were already told about it.
- **No backfill.** Only listings that go live after a search is saved create alerts. Each search links to the listings that already match. *Trade-off:* a new search shows no alerts until something new is listed.
- **Criteria: the four existing filters, with a max price rather than a range.** Buyers think in budgets, and keeping saved searches identical to the filters means the alerts always agree with the page. *Trade-off:* no minimum price and no multiple property types yet.
- **Deleting a search keeps its alerts,** because the alert is about the listing.
- **I fixed the auth stub in its own commit.** `auth()->user()` returned null, because `ActAsDemoUser` only set the request's user resolver.

## With more time
- **Email:** a daily digest of unread alerts rather than one email per alert, which needs read/unread state and an unsubscribe link.
- **Criteria:** more than one property type per search, then a price range in the filters and the saved searches together.
- **Searches:** editing and pausing a search, plus price-drop and back-on-the-market alerts.
- **Missed listings:** a scheduled sweep for listings that went live without model events.
- **Listings page:** eager-load `branch` on the listings index to fix an existing N+1 query.

## Where it wouldn't hold up yet
Measured on SQLite with random criteria:
- **Memory is the first limit.** The job loads every match, about 1.4 KB each, so a 128 MB worker fails at around 90k matches for one listing. The fix is one `INSERT … SELECT` grouped by user.
- **The scan is fine for now.** It takes about 1 ms at 10k saved searches, 7 ms at 100k and 75 ms at 1M. It reads every search, because "empty or matches" can't use an index. Narrowing by region or type first would be the next step.
- **Inserts hit SQLite's bound-value limit** above about 6,500 users, so the job now inserts in batches of 1,000.
- **Imports and bulk updates don't alert** unless they dispatch the job themselves.
- **Region lives on the branch, not the listing.** A Leeds house listed by a Manchester branch never matches a Leeds search.
- **Alerts are never deleted,** so the table grows without limit.
- **Only tested as the stub demo user.** There's no real multi-user auth, and no test run through a real queue worker.
