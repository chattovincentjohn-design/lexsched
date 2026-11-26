@extends('layouts.app')
@section('title', 'My appointments')
@section('content')
<div class="pagehead">
    <div><h1>My appointments</h1><p>Requests stay pending until the secretary confirms them. You'll get a text either way.</p></div>
    <a class="btn primary" href="{{ route('client.book') }}">+ Book a consultation</a>
</div>

@if ($next)
    <div class="panel next-card" style="margin-bottom:20px">
        <div class="datebox"><span>{{ $next->scheduled_date->format('M') }}</span><b>{{ $next->scheduled_date->format('j') }}</b></div>
        <div style="flex:1;min-width:220px">
            <div class="label">Next appointment</div>
            <h2 style="margin:2px 0 4px">{{ $next->scheduled_date->format('l, F j') }} · {{ $next->timeLabel() }}</h2>
            <p class="muted">{{ $next->lawyer->name }} · {{ $next->service->name }}{{ $next->notarialDocument ? ' – '.$next->notarialDocument->name : '' }} · {{ $next->modeLabel() }}</p>
            <div class="row-flex" style="margin-top:8px"><x-status :status="$next->status" /><span class="tag">{{ $next->paymentLabel() }}</span></div>
        </div>
        <a class="btn" href="{{ route('client.appointments.show', $next) }}">View details</a>
    </div>
@endif

<div class="panel" style="padding:6px 0">
    <div class="scroll">
        <table>
            <thead><tr><th>Reference</th><th>Date &amp; time</th><th>Lawyer</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($appointments as $appointment)
                <tr class="row" data-href="{{ route('client.appointments.show', $appointment) }}">
                    <td class="mono"><a class="rowlink" href="{{ route('client.appointments.show', $appointment) }}">{{ $appointment->reference }}</a></td>
                    <td class="num">{{ $appointment->scheduled_date->format('D, M j, Y') }} · {{ $appointment->timeLabel() }}</td>
                    <td>{{ $appointment->lawyer->name }}</td>
                    <td>{{ $appointment->service->name }}@if ($appointment->notarialDocument)<br><span class="small muted">{{ $appointment->notarialDocument->name }}</span>@endif</td>
                    <td><x-status :status="$appointment->status" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No appointments yet. <a href="{{ route('client.book') }}">Book your first consultation.</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
