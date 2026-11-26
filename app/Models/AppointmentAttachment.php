<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentAttachment extends Model
{
    /** @var list<string> */
    protected $fillable = ['appointment_id', 'original_name', 'path', 'size'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
