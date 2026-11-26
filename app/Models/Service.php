<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['slug', 'name', 'description', 'fee', 'duration_minutes', 'is_notarial', 'is_active'];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_notarial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lawyers(): BelongsToMany
    {
        return $this->belongsToMany(Lawyer::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
