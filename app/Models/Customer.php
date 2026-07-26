<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'team_id', 'created_by',
        'name', 'first_name', 'last_name',
        'mobile', 'email', 'city', 'notes', 'last_activity_at',
        'website', 'business_name', 'house_no', 'address_1', 'address_2',
        'additional_info', 'merged_into_id', 'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    /**
     * Keep the full name and its two halves saying the same thing.
     *
     * A contact's name arrives three different ways — whole from Facebook or a
     * walk-in, split from the client's import template, and either from a form
     * — and every screen reads `name` while the template reads the halves. Done
     * here rather than in each caller so no path can write one and forget the
     * others: a seeder, an import, a merge and a hand edit all pass through.
     *
     * Whichever side was written wins, halves first — they are the more
     * specific statement, and a caller that knows them knows the split. The
     * contract that goes with that: write the halves as a pair or not at all.
     * One half is not a split, and writing it alone over a whole name would
     * lose the other half.
     */
    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            if ($customer->isDirty(['first_name', 'last_name'])) {
                $rebuilt = trim(
                    trim((string) $customer->first_name).' '.trim((string) $customer->last_name)
                );

                // Backstop for a caller that broke the pair rule: a contact
                // must never end up nameless because of a bookkeeping field.
                if ($rebuilt !== '') {
                    $customer->name = $rebuilt;
                }

                return;
            }

            if ($customer->isDirty('name')) {
                [$first, $last] = self::splitName($customer->name);

                $customer->first_name = $first;
                $customer->last_name = $last;
            }
        });
    }

    /**
     * Split a whole name for the two-column template.
     *
     * On the *last* space, so "Asha Devi Rao" keeps its middle name with the
     * first rather than dropping it. Names that do not split at all — one word,
     * a business — keep everything in the first half and leave the last empty,
     * which is truthful rather than inventing a surname.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function splitName(?string $name): array
    {
        $name = trim((string) $name);
        $cut = mb_strrpos($name, ' ');

        return $cut === false
            ? [$name, null]
            : [mb_substr($name, 0, $cut), mb_substr($name, $cut + 1)];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /** Every note written about this person, whichever lead prompted it. */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** The organisation whose book this contact sits in. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The most recent enquiry, for the columns that show one value per contact
     * — stage, source, owner, deal value.
     *
     * latestOfMany rather than loading every lead and taking the first: this
     * stays one extra query for the whole page instead of one per row.
     */
    public function latestLead(): HasOne
    {
        return $this->hasOne(Lead::class)->latestOfMany();
    }

    /** Whoever typed this contact in; null for anyone the sync brought in. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Labels on the person, as against the campaign that found them. */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'customer_tag');
    }

    /** Every phone number and email address this customer can be reached on. */
    public function channels(): HasMany
    {
        return $this->hasMany(CustomerChannel::class);
    }

    public function phones(): HasMany
    {
        return $this->channels()->where('type', CustomerChannel::PHONE)->orderByDesc('is_primary');
    }

    public function emails(): HasMany
    {
        return $this->channels()->where('type', CustomerChannel::EMAIL)->orderByDesc('is_primary');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** Records that were folded into this one. */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    /** Exclude records that have been merged away. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'email', 'merged_into_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer');
    }
}
