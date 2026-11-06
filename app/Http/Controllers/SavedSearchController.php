<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\SavedSearchResource;
use App\Models\Branch;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection(
                $request->user()->savedSearches()->orderByDesc('id')->get()
            ),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
        ]);
    }

    /**
     * Saving the same criteria twice keeps one search. Omitted criteria are
     * filled as null so a narrower existing search isn't mistaken for this one.
     */
    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $criteria = [
            ...array_fill_keys(SavedSearch::CRITERIA, null),
            ...$request->validated(),
        ];

        $request->user()->savedSearches()->firstOrCreate($criteria);

        return to_route('saved-searches.index');
    }

    /**
     * 404 rather than 403 for someone else's search, so its existence isn't
     * confirmed.
     */
    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $savedSearch->delete();

        return to_route('saved-searches.index');
    }
}
