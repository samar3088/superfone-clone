<?php

namespace App\Models;

use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'is_active',
        'must_reset_password',
        'team_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Owners administer the system and can never be deleted. */
    public function isOwner(): bool
    {
        return $this->hasRole(Roles::OWNER);
    }

    /** Record who changed what, without ever logging credentials. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'mobile', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    /**
     * Fall back to the first organisation when nobody names one.
     *
     * Belt and braces over setting it in each caller: seeders, factories and a
     * quick tinker session all create users, and a member with no team drops
     * out of every team-filtered view without any obvious cause.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->team_id ??= Team::query()->orderBy('id')->value('id');
        });
    }

    /** The organisation this member belongs to. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
