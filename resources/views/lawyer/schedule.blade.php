@extends('layouts.app')
@section('title', 'My schedule')
@section('content')
@php($rows = ['09:00', '10:00', '11:00', 'lunch', '13:00', '14:00', '15:00', '16:00'])
@php($prevWeek = $weekStart->copy()->subWeek()->toDateString())
@php($nextWeek = $weekStart->copy()->addWeek()->toDateString())
<div class="pagehead">
    <div><h1>My schedule</h1><p>{{ $todayCount }} confirmed for today · {{ $pendingCount }} waiting for the secretary's approval</p></div>
    <div class="row-flex">
        <label class="small muted" for="block-reason">Reason when blocking an hour</label>
        <select class="input" id="block-reason" style="width:auto">
            @foreach ($reasons as $reason)<option>{{ $reason }}</option>@endforeach
        </select>
    </div>
</div>
@error('block')<div class="alert bad">{{ $message }}</div>@enderror

<div class="split">
    <div class="panel">
        <div class="row-flex between" style="margin-bottom:14px">
            <div class="row-flex">
                <a class="btn sm" href="{{ route('lawyer.schedule', ['week' => $prevWeek]) }}" aria-label="Previous week">‹</a>
                <h2 class="num">{{ $days->first()->format('M j') }} – {{ $days->last()->format('M j, Y') }}</h2>
                <a class="btn sm" href="{{ route('lawyer.schedule', ['week' => $nextWeek]) }}" aria-label="Next week">›</a>
            </div>
            <a class="btn sm" href="{{ route('lawyer.schedule') }}">This week</a>
        </div>
        <div class="scroll">
            <div class="week">
                <div class="wh"></div>
                @foreach ($days as $day)<div @class(['wh', 'today' => $day->isToday()])>{{ $day->format('D j') }}</div>@endforeach
                @foreach ($rows as $time)
                    @if ($time === 'lunch')
                        <div class="t lunch">12:00</div>
                        @foreach ($days as $day)<div class="lunch">Lunch</div>@endforeach
                        @continue
                    @endif
                    <div class="t">{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('g A') }}</div>
                    @foreach ($days as $day)
                        @php($key = $day->toDateString().'|'.$time)
                        @php($appointment = $appointments->get($key))
                        @php($block = $blocks->first(fn ($b) => $b->date->isSameDay($day) && ($b->start_time === $time || $b->start_time === null)))
                        @php($isPast = \Illuminate\Support\Carbon::parse($day->toDateString().' '.$time)->isPast())
                        <div @class(['past' => $isPast && ! $appointment && ! $block])>
                            @if ($appointment)
                                <a href="{{ route('lawyer.schedule', ['week' => $weekStart->toDateString(), 'selected' => $appointment->reference]) }}" @class(['ev', 'st-'.$appointment->status->value, 'sel' => $selected?->is($appointment)])>
                                    <b>{{ $appointment->client_name }}</b>{{ $appointment->notarialDocument?->name ?? $appointment->service->name }}{{ $appointment->mode === 'video' ? ' · Video' : '' }}
                                </a>
                            @elseif ($block)
                                <form method="POST" action="{{ route('lawyer.blocks.destroy', $block) }}" data-confirm="Remove this block{{ $block->isWholeDay() ? ' for the whole day' : '' }}?">
                                    @csrf @method('DELETE')
                                    <button class="ev blk" title="Click to remove"><b>{{ $block->reason }}</b>{{ $block->isWholeDay() ? 'Whole day' : 'Blocked' }}</button>
                                </form>
                            @elseif (! $isPast)
                                <form method="POST" action="{{ route('lawyer.blocks.store') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                                    <input type="hidden" name="time" value="{{ $time }}">
                                    <input type="hidden" name="reason" value="{{ $reasons[0] }}" data-reason>
                                    <button class="cellbtn" aria-label="Block {{ $day->format('M j') }} {{ $time }}">+ Block</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
        <div class="legend">
            <span><i style="background:var(--a-bg)"></i>Approved</span><span><i style="background:var(--p-bg)"></i>Pending</span>
            <span><i style="background:var(--c-bg)"></i>Completed</span><span><i style="background:var(--block)"></i>Blocked</span>
            <span>Click an empty hour to block it.</span>
        </div>
        <form method="POST" action="{{ route('lawyer.blocks.store') }}" class="row-flex" style="margin-top:16px">
            @csrf
            <label class="small" for="whole-day"><b>Block a whole day</b></label>
            <input class="input" id="whole-day" type="date" name="date" min="{{ today()->toDateString() }}" style="width:auto" required>
            <input type="hidden" name="reason" value="{{ $reasons[0] }}" data-reason>
            <button class="btn sm">Block day</button>
        </form>
    </div>

    @if ($selected)
        <aside class="panel detail stack">
            <div class="row-flex between"><span class="mono muted">{{ $selected->reference }}</span><x-status :status="$selected->status" /></div>
            <h2>{{ $selected->client_name }}</h2>
            <dl class="kv">
                <dt>Schedule</dt><dd>{{ $selected->scheduleLabel() }}</dd>
                <dt>Service</dt><dd>{{ $selected->service->name }}</dd>
                @if ($selected->notarialDocument)<dt>Document</dt><dd>{{ $selected->notarialDocument->name }} · {{ $selected->copies }} {{ Str::plural('copy', $selected->copies) }}</dd>@endif
                <dt>Mode</dt><dd>{{ $selected->modeLabel() }}@if ($selected->videoLink())<br><a class="small" href="{{ $selected->videoLink() }}" target="_blank" rel="noopener">Open video room</a>@endif</dd>
                @unless ($selected->isNotarial())<dt>Opposing party</dt><dd>{{ $selected->opposing_party ?: '—' }}</dd>@endunless
                <dt>Contact</dt><dd>{{ $selected->client_phone ?: '—' }}</dd>
                @foreach ($selected->attachments as $attachment)<dt>Attachment</dt><dd><a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a></dd>@endforeach
            </dl>
            @if ($selected->concern)<div><div class="label" style="margin-bottom:6px">Client's concern</div><div class="concern">{{ $selected->concern }}</div></div>@endif
            <form method="POST" action="{{ route('lawyer.appointments.notes', $selected) }}" class="stack">
                @csrf @method('PATCH')
                <div class="field"><label for="lawyer_notes">Private consultation notes</label>
                    <textarea id="lawyer_notes" name="lawyer_notes" maxlength="5000" placeholder="Only lawyers can see these notes">{{ old('lawyer_notes', $selected->lawyer_notes) }}</textarea></div>
                <button class="btn sm">Save notes</button>
            </form>
            @if ($selected->status === \App\Enums\AppointmentStatus::Approved && ! $selected->isNotarial())
                <form method="POST" action="{{ route('lawyer.appointments.complete', $selected) }}">@csrf<button class="btn primary block">Mark consultation completed</button></form>
            @elseif ($selected->isNotarial() && $selected->status === \App\Enums\AppointmentStatus::Approved)
                <p class="hint">The secretary records notarized documents in your register after checking the signatory's ID.</p>
            @endif
        </aside>
    @else
        <aside class="panel detail stack">
            <h3>{{ $lawyer->name }}</h3>
            <p class="small muted">{{ $lawyer->services()->pluck('name')->implode(' · ') }}</p>
            @if ($lawyer->hasValidNotarialCommission())
                <p class="small">Notary public · Commission No. {{ $lawyer->notarial_commission_no }}, until {{ $lawyer->commission_expires_on->format('M j, Y') }}</p>
            @endif
            <p class="muted">Select an appointment to read the client's concern and write private notes.</p>
        </aside>
    @endif
</div>
@push('scripts')
<script>
    document.getElementById('block-reason').addEventListener('change', function () {
        document.querySelectorAll('[data-reason]').forEach((input) => { input.value = this.value; });
    });
</script>
@endpush
@endsection
