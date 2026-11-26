<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotarialEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'appointment_id', 'lawyer_id', 'series', 'doc_no', 'page_no', 'book_no',
        'notarized_on', 'document_name', 'principal_name', 'id_type', 'copies', 'fee',
    ];

    protected function casts(): array
    {
        return [
            'notarized_on' => DateOnly::class,
            'fee' => 'decimal:2',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    /**
     * The line printed on the notarial certificate.
     */
    public function certificateLine(): string
    {
        return "Doc. No. {$this->doc_no}; Page No. {$this->page_no}; Book No. {$this->book_no}; Series of {$this->series}";
    }
}
