<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * The criteria a search can set. Adding one means updating this list,
     * the migration, the casts, scopeMatching() and the request rules.
     */
    public const CRITERIA = ['max_price', 'min_bedrooms', 'property_type', 'region'];

    protected $fillable = self::CRITERIA;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'property_type' => PropertyType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Saved searches whose criteria the given listing satisfies. A null
     * criterion means "any"; every criterion that is set must match.
     *
     * This mirrors the listings index filters, but in the reverse direction
     * (listing -> searches) so one query finds every search to alert.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatching(Builder $query, Listing $listing): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $q) => $q->whereNull('region')->orWhere('region', $listing->branch->region));
    }
}
