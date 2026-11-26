<?php

namespace App\Models;

use App\Casts\DateOnly;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    /**
     * Holiday dates as "Y-m-d" strings, for quick lookups.
     *
     * @return list<string>
     */
    public static function dates(): array
    {
        return self::query()->toBase()->pluck('date')->all();
    }
}
