<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSlot extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['lawyer_id', 'date', 'start_time', 'reason'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    public function isWholeDay(): bool
    {
        return $this->start_time === null;
    }
}
