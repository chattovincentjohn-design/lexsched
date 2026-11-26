<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory;

    public const MODES = ['in_office' => 'In-office', 'video' => 'Video call'];

    public const PAYMENT_METHODS = [
        'gcash' => 'GCash', 'maya' => 'Maya', 'card' => 'Card', 'office' => 'Pay at office',
        'cash' => 'Cash', 'online' => 'Online (PayMongo)',
    ];

    /** @var list<string> */
    protected $fillable = [
        'client_id', 'client_name', 'client_phone', 'client_email', 'lawyer_id', 'service_id',
        'scheduled_date', 'start_time', 'status', 'mode', 'source', 'concern', 'opposing_party',
        'fee', 'payment_method', 'payment_reference', 'paid_at', 'official_receipt_no', 'paymongo_checkout_id', 'paymongo_payment_id', 'notarial_document_id', 'copies',
        'check_appeared', 'check_id_presented', 'check_document_complete', 'id_type',
        'lawyer_notes', 'decline_reason', 'reminded_at', 'reschedule_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'scheduled_date' => DateOnly::class,
            'fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
            'check_appeared' => 'boolean',
            'check_id_presented' => 'boolean',
            'check_document_complete' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->reference ??= self::newReference();
        });

        // Keep slot_key in sync so the unique index blocks double booking.
        static::saving(function (Appointment $appointment): void {
            $appointment->slot_key = $appointment->status->holdsSlot()
                ? self::slotKey($appointment->lawyer_id, $appointment->scheduled_date->toDateString(), $appointment->start_time)
                : null;
        });
    }

    public static function slotKey(int $lawyerId, string $date, string $time): string
    {
        return "{$lawyerId}|{$date}|{$time}";
    }

    public static function newReference(): string
    {
        do {
            $reference = 'APT-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function notarialDocument(): BelongsTo
    {
        return $this->belongsTo(NotarialDocument::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AppointmentAttachment::class);
    }

    public function notarialEntry(): HasOne
    {
        return $this->hasOne(NotarialEntry::class);
    }

    public function scopeStatus(Builder $query, AppointmentStatus ...$statuses): void
    {
        $query->whereIn('status', array_map(fn (AppointmentStatus $status) => $status->value, $statuses));
    }

    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('scheduled_date')->orderBy('start_time');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->scheduled_date->toDateString().' '.$this->start_time);
    }

    public function timeLabel(): string
    {
        return Carbon::createFromFormat('H:i', $this->start_time)->format('g:i A');
    }

    public function scheduleLabel(): string
    {
        return $this->scheduled_date->format('D, M j, Y').' · '.$this->timeLabel();
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    public function paymentLabel(): string
    {
        $method = self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;

        return ($this->isPaid() ? 'Paid' : 'Unpaid').' · '.$method;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * Why the client can't move this appointment themselves, or null if they can.
     */
    public function clientRescheduleBlocker(): ?string
    {
        if (! $this->status->isCancellable()) {
            return 'This appointment can no longer be moved.';
        }

        $cutoff = (int) config('lexsched.reschedule_cutoff_hours');

        if ($this->startsAt()->lt(now()->addHours($cutoff))) {
            return "Appointments can be moved online until {$cutoff} hours before they start. Please call the office.";
        }

        if ($this->reschedule_count >= (int) config('lexsched.max_client_reschedules')) {
            return 'You have already moved this appointment the maximum number of times. Please call the office.';
        }

        return null;
    }

    public function wasPaidOnline(): bool
    {
        return $this->paymongo_payment_id !== null;
    }

    /**
     * Whether the client can still pay online: unpaid, still active, and PayMongo is set up.
     */
    public function canPayOnline(): bool
    {
        return ! $this->isPaid()
            && $this->status->isCancellable()
            && filled(config('lexsched.paymongo.secret_key'));
    }

    public function isNotarial(): bool
    {
        return $this->notarial_document_id !== null;
    }

    public function isUpcoming(): bool
    {
        return $this->startsAt()->isFuture();
    }

    public function notarialChecklistComplete(): bool
    {
        return $this->check_appeared && $this->check_id_presented && $this->check_document_complete && filled($this->id_type);
    }

    public function videoLink(): ?string
    {
        return $this->mode === 'video' ? 'https://meet.jit.si/lexsched-'.Str::lower($this->reference) : null;
    }
}
