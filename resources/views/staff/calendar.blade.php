@extends('layouts.app')
@section('title', 'Day calendar')
@section('content')
@include('staff.subnav')
@php($prev = $day->copy()->subDay()->toDateString())
@php($next = $day->copy()->addDay()->toDateString())
<div class="pagehead">
    <div><h1>{{ $day->format('l, F j, Y') }}</h1>
        <p>{{ $appointments->count() }} {{ Str::plural('appointment', $appointments->count()) }}
            @if ($holiday) · <b>{{ $holiday->name }}</b> (office closed)@elseif ($day->isWeekend()) · weekend (office closed)@endif</p></div>
    <form method="GET" class="row-flex">
        <a class="btn sm" href="{{ route('staff.calendar', ['date' => $prev]) }}" aria-label="Previous day">‹</a>
        <label class="sr-only" for="date">Date</label>
        <input class="input" type="date" id="date" name="date" value="{{ $day->toDateString() }}" style="width:auto" onchange="this.form.submit()">
        <a class="btn sm" href="{{ route('staff.calendar', ['date' => $next]) }}" aria-label="Next day">›</a>
        <a class="btn sm" href="{{ route('staff.calendar') }}">Today</a>
    </form>
</div>

<div class="panel">
    <div class="scroll">
        <div class="week" style="grid-template-columns:64px repeat({{ max(1, $lawyers->count()) }}, minmax(150px, 1fr));min-width:{{ 64 + 150 * max(1, $lawyers->count()) }}px">
            <div class="wh"></div>
            @foreach ($lawyers as $lawyer)<div class="wh">{{ $lawyer->name }}</div>@endforeach
            @foreach ($slots as $index => $time)
                @if ($time === '13:00')
                    <div class="t lunch">12:00</div>
                    @foreach ($lawyers as $lawyer)<div class="lunch">Lunch</div>@endforeach
                @endif
                <div class="t">{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('g A') }}</div>
                @foreach ($lawyers as $lawyer)
                    @php($appointment = $appointments->get("{$lawyer->id}|{$time}"))
                    @php($block = $blocks->first(fn ($b) => $b->lawyer_id === $lawyer->id && ($b->start_time === $time || $b->start_time === null)))
                    @php($isPast = \Illuminate\Support\Carbon::parse($day->toDateString().' '.$time)->addMinutes(59)->isPast())
                    <div @class(['past' => $isPast && ! $appointment && ! $block])>
                        @if ($appointment)
                            <a class="ev st-{{ $appointment->status->value }}" href="{{ route('staff.appointments.index', ['filter' => 'all', 'q' => $appointment->reference, 'selected' => $appointment->reference]) }}">
                                <b>{{ $appointment->client_name }}</b>{{ $appointment->notarialDocument?->name ?? $appointment->service->name }}{{ $appointment->source === 'walk_in' ? ' · Walk-in' : '' }}
                            </a>
                        @elseif ($block)
                            <div class="ev blk"><b>{{ $block->reason }}</b>{{ $block->isWholeDay() ? 'Whole day' : 'Blocked' }}</div>
                        @elseif (! $isPast && ! $holiday && ! $day->isWeekend())
                            <a class="cellbtn" style="display:grid;place-items:center;text-decoration:none" href="{{ route('staff.walk-in.create', ['date' => $day->toDateString(), 'time' => $time, 'lawyer' => $lawyer->id]) }}">+ Walk-in</a>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
    <div class="legend">
        <span><i style="background:var(--a-bg)"></i>Approved</span><span><i style="background:var(--p-bg)"></i>Pending</span>
        <span><i style="background:var(--c-bg)"></i>Completed</span><span><i style="background:var(--n-bg)"></i>No-show</span><span><i style="background:var(--block)"></i>Blocked</span>
        <span>Click an open hour to add a walk-in.</span>
    </div>
</div>
@endsection
