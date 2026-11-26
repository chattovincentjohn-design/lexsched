<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use App\Models\Holiday;
use App\Models\Lawyer;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out which dates and hours can still be booked, taking into account weekends, holidays,
 * the booking window, existing appointments and hours blocked by lawyers.
 */
class AvailabilityService
{
    /**
     * Minutes after a slot starts during which it can still be booked (used for walk-ins).
     */
    private int $graceMinutes = 0;

    /**
     * @var list<string>|null
     */
    private ?array $holidayDates = null;

    /**
     * A copy that still offers the slot that started within the last hour, for clients already at the office.
     */
    public function forWalkIns(): static
    {
        $copy = clone $this;
        $copy->graceMinutes = 59;

        return $copy;
    }

    /**
     * @return list<string>
     */
    public function slots(): array
    {
        return config('lexsched.slots');
    }

    /**
     * Lawyers who can take a booking for the service. Notarial work also needs a valid commission.
     *
     * @return Collection<int, Lawyer>
     */
    public function lawyersFor(Service $service): Collection
    {
        return Lawyer::query()
            ->bookable()
            ->whereHas('services', fn (Builder $query) => $query->whereKey($service->id))
            ->with('user')
            ->get()
            ->filter(fn (Lawyer $lawyer) => ! $service->is_notarial || $lawyer->hasValidNotarialCommission())
            ->values();
    }

    public function isOfficeDay(CarbonInterface $date): bool
    {
        $this->holidayDates ??= Holiday::dates();

        return ! $date->isWeekend() && ! in_array($date->toDateString(), $this->holidayDates, true);
    }

    public function isWithinBookingWindow(CarbonInterface $date): bool
    {
        return $date->betweenIncluded(today(), today()->addDays(config('lexsched.booking_window_days')));
    }

    public function isPastTime(string $date, string $time): bool
    {
        return Carbon::parse("{$date} {$time}")->addMinutes($this->graceMinutes)->lte(now());
    }

    public function isSlotFree(Lawyer $lawyer, string $date, string $time): bool
    {
        return in_array($time, $this->freeSlots($lawyer, $date), true);
    }

    /**
     * Free start times for one lawyer on one date.
     *
     * @return list<string>
     */
    public function freeSlots(Lawyer $lawyer, string $date): array
    {
        return $this->openSlots(collect([$lawyer]), $date)->keys()->all();
    }

    /**
     * Open times for a service on a date, each with the ids of the lawyers free at that time.
     *
     * @return Collection<string, list<int>>
     */
    public function openSlotsForService(Service $service, ?Lawyer $lawyer, string $date): Collection
    {
        $lawyers = $lawyer ? collect([$lawyer]) : $this->lawyersFor($service);

        return $this->openSlots($lawyers, $date);
    }

    /**
     * Dates in the month that still have at least one open time.
     *
     * @return list<string>
     */
    public function openDays(Service $service, ?Lawyer $lawyer, CarbonInterface $month): array
    {
        $lawyers = $lawyer ? collect([$lawyer]) : $this->lawyersFor($service);
        $start = $month->copy()->startOfMonth()->max(today());
        $end = $month->copy()->endOfMonth()->min(today()->addDays(config('lexsched.booking_window_days')));

        if ($lawyers->isEmpty() || $start->gt($end)) {
            return [];
        }

        $occupancy = $this->occupancy($lawyers, $start->toDateString(), $end->toDateString());
        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($this->openSlots($lawyers, $day->toDateString(), $occupancy)->isNotEmpty()) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    /**
     * @param  Collection<int, Lawyer>  $lawyers
     * @param  array{taken: array<string, true>, blocked: array<string, true>}|null  $occupancy
     * @return Collection<string, list<int>>
     */
    private function openSlots(Collection $lawyers, string $date, ?array $occupancy = null): Collection
    {
        $day = Carbon::parse($date);

        if ($lawyers->isEmpty() || ! $this->isOfficeDay($day) || ! $this->isWithinBookingWindow($day)) {
            return collect();
        }

        $occupancy ??= $this->occupancy($lawyers, $date, $date);
        $open = collect();

        foreach ($this->slots() as $time) {
            if ($this->isPastTime($date, $time)) {
                continue;
            }

            $free = $lawyers
                ->filter(fn (Lawyer $lawyer) => ! isset($occupancy['taken'][Appointment::slotKey($lawyer->id, $date, $time)])
                    && ! isset($occupancy['blocked']["{$lawyer->id}|{$date}|{$time}"])
                    && ! isset($occupancy['blocked']["{$lawyer->id}|{$date}|*"]))
                ->pluck('id')
                ->values()
                ->all();

            if ($free !== []) {
                $open->put($time, $free);
            }
        }

        return $open;
    }

    /**
     * Loads every taken and blocked hour for the lawyers in one go.
     *
     * @param  Collection<int, Lawyer>  $lawyers
     * @return array{taken: array<string, true>, blocked: array<string, true>}
     */
    private function occupancy(Collection $lawyers, string $from, string $to): array
    {
        $ids = $lawyers->pluck('id');

        $taken = Appointment::query()
            ->whereIn('lawyer_id', $ids)
            ->whereBetween('scheduled_date', [$from, $to])
            ->whereNotNull('slot_key')
            ->pluck('slot_key')
            ->mapWithKeys(fn (string $key) => [$key => true])
            ->all();

        $blocked = BlockedSlot::query()
            ->whereIn('lawyer_id', $ids)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->mapWithKeys(fn (BlockedSlot $block) => [
                "{$block->lawyer_id}|{$block->date->toDateString()}|".($block->start_time ?? '*') => true,
            ])
            ->all();

        return ['taken' => $taken, 'blocked' => $blocked];
    }

    /**
     * Statuses that keep a slot occupied, for reference in queries elsewhere.
     *
     * @return list<AppointmentStatus>
     */
    public static function occupyingStatuses(): array
    {
        return array_values(array_filter(AppointmentStatus::cases(), fn (AppointmentStatus $status) => $status->holdsSlot()));
    }
}
