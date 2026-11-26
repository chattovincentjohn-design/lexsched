<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lawyer extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'roll_number', 'ibp_chapter', 'years_of_practice',
        'notarial_commission_no', 'commission_expires_on', 'bio', 'accepts_bookings',
    ];

    protected function casts(): array
    {
        return [
            'commission_expires_on' => DateOnly::class,
            'accepts_bookings' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function blockedSlots(): HasMany
    {
        return $this->hasMany(BlockedSlot::class);
    }

    public function scopeBookable(Builder $query): void
    {
        $query->where('accepts_bookings', true)->whereHas('user', fn (Builder $user) => $user->where('is_active', true));
    }

    public function getNameAttribute(): string
    {
        return 'Atty. '.$this->user->name;
    }

    public function hasValidNotarialCommission(?string $onDate = null): bool
    {
        return $this->notarial_commission_no !== null
            && $this->commission_expires_on !== null
            && $this->commission_expires_on->gte($onDate ?? today());
    }
}
