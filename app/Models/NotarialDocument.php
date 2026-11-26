<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotarialDocument extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'fee', 'requirements', 'is_active'];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'requirements' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Total fee for the given number of copies; the first copy is included in the base fee.
     */
    public function feeFor(int $copies): float
    {
        return (float) $this->fee + max(0, $copies - 1) * (float) config('lexsched.extra_copy_fee');
    }
}
