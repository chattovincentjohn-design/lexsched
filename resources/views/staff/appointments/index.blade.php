@extends('layouts.app')
@section('title', 'Appointments')
@section('content')
@include('staff.subnav')
<div class="pagehead">
    <div><h1>Appointments</h1><p>{{ $counts['pending'] }} {{ Str::plural('request', $counts['pending']) }} waiting for approval · {{ $counts['today'] }} today</p></div>
    <form method="GET" class="row-flex">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <label class="sr-only" for="q">Search</label>
        <input class="input" id="q" name="q" value="{{ $search }}" placeholder="Search name or reference" style="width:240px">
        <button class="btn">Search</button>
    </form>
</div>

<div class="tabs" style="margin-bottom:16px">
    @foreach (\App\Http\Controllers\Staff\AppointmentController::FILTERS as $key => $label)
        <a href="{{ route('staff.appointments.index', ['filter' => $key, 'q' => $search ?: null]) }}" @class(['on' => $filter === $key])>{{ $label }}<span class="count">{{ $counts[$key] }}</span></a>
    @endforeach
</div>

<div class="split">
    <div class="panel" style="padding:6px 0">
        <div class="scroll">
            <table>
                <thead><tr><th>Reference</th><th>Client</th><th>Lawyer</th><th>Schedule</th><th>Service</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    @php($href = route('staff.appointments.index', ['filter' => $filter, 'q' => $search ?: null, 'page' => $appointments->currentPage(), 'selected' => $appointment->reference]))
                    <tr @class(['row', 'sel' => $selected?->is($appointment)]) data-href="{{ $href }}">
                        <td class="mono"><a class="rowlink" href="{{ $href }}">{{ $appointment->reference }}</a></td>
                        <td><b>{{ $appointment->client_name }}</b>
                            @if ($appointment->source === 'walk_in') <span class="tag">Walk-in</span>@endif
                            @if ($flagged[$appointment->id] ?? false) <span class="chip st-conflict">Conflict</span>@endif</td>
                        <td>{{ Str::after($appointment->lawyer->name, 'Atty. ') }}</td>
                        <td class="num">{{ $appointment->scheduled_date->format('D, M j') }} · {{ $appointment->timeLabel() }}</td>
                        <td>{{ $appointment->service->name }}@if ($appointment->notarialDocument)<br><span class="small muted">{{ $appointment->notarialDocument->name }}</span>@endif</td>
                        <td><x-status :status="$appointment->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Nothing here. Try another filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($appointments->hasPages())
            <div class="pager">
                <span class="muted">Page {{ $appointments->currentPage() }} of {{ $appointments->lastPage() }}</span>
                <span class="row-flex">
                    @if ($appointments->previousPageUrl())<a class="btn sm" href="{{ $appointments->previousPageUrl() }}">‹ Previous</a>@endif
                    @if ($appointments->nextPageUrl())<a class="btn sm" href="{{ $appointments->nextPageUrl() }}">Next ›</a>@endif
                </span>
            </div>
        @endif
    </div>

    @if ($selected)
        @include('staff.appointments.detail', ['appointment' => $selected])
    @else
        <aside class="panel empty">Select an appointment to see its details.</aside>
    @endif
</div>
@endsection
