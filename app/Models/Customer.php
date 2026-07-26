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
        'name', 'first_name', 'last_name', 'name_key',
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
                [$first, $last] = self::nameParts($customer->name);

                $customer->first_name = $first;
                $customer->last_name = $last;
            }
        });

        // After the name is settled, whichever side set it.
        static::saving(function (self $customer): void {
            if ($customer->isDirty('name')) {
                $customer->name_key = self::nameKey($customer->name);
            }
        });

        /*
         | Every number and address a contact holds must exist as a channel row,
         | because that table is what answers "have we met this person before?".
         |
         | The columns on the contact are a convenience for lists and exports;
         | the channels are the identity. A contact written straight to the
         | table — a seeder, a fixture, a one-off script — would carry a mobile
         | that matches nothing, and the next enquiry from that number would
         | quietly open a second record for the same person.
         |
         | Enforced here rather than asked of every caller, for the same reason
         | the name is: one path forgetting is all it takes.
         */
        static::saved(function (self $customer): void {
            // Nothing to keep reachable about a record that has been merged
            // away — and its mobile has been stamped with a tombstone suffix.
            if ($customer->merged_into_id) {
                return;
            }

            if (! $customer->wasRecentlyCreated && ! $customer->wasChanged(['mobile', 'email'])) {
                return;
            }

            foreach ([
                CustomerChannel::PHONE => $customer->mobile,
                CustomerChannel::EMAIL => $customer->email,
            ] as $type => $raw) {
                $value = blank($raw) ? null : CustomerChannel::normalise($type, (string) $raw);

                if ($value === null) {
                    continue;
                }

                /*
                 | firstOrCreate on the unique pair, so if somebody else already
                 | holds this value it is left with them. One number cannot
                 | belong to two contacts, and which of them it is is a merge
                 | decision rather than something to settle here.
                 */
                CustomerChannel::firstOrCreate(
                    ['type' => $type, 'value' => $value],
                    ['customer_id' => $customer->id, 'is_primary' => true],
                );
            }
        });
    }

    /**
     * A name reduced to something comparable, for finding duplicates.
     *
     * Lower case, punctuation dropped, runs of spaces collapsed — so "Asha
     * Rao", "asha rao" and "Asha  Rao." all key the same and group together.
     * Deliberately not clever: no phonetics, no nicknames. A key that matches
     * too eagerly puts unrelated people in front of someone about to merge
     * them, and one bad merge costs more than ten missed ones.
     */
    public static function nameKey(?string $name): string
    {
        $key = mb_strtolower(trim((string) $name));
        $key = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $key);

        return trim(preg_replace('/\s+/', ' ', (string) $key));
    }

    /**
     * Where a whole name goes when that is all the source gave us.
     *
     * All of it into the first name, none of it into the last. A whole name is
     * not a split, and there is no rule that finds one: "Asha Devi Rao" has no
     * knowable boundary, and plenty of names have no surname at all. Guessing
     * would be right often enough to be trusted and wrong often enough to
     * matter — and once written, a guess reads as fact.
     *
     * The last name is filled only when something actually told us it: the
     * client's two-column import template, a Facebook form with separate
     * fields, or a person typing into the two boxes on the contact form. Anyone
     * can correct it afterwards, which a derived value would undo on next save.
     *
     * @return array{0: string, 1: null}
     */
    public static function nameParts(?string $name): array
    {
        return [trim((string) $name), null];
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
