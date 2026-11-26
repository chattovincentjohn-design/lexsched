@extends('layouts.app')
@section('title', 'Office overview')
@section('content')
<div class="pagehead">
    <div><h1>Office overview</h1><p>{{ today()->format('l, F j, Y') }} · figures for {{ $monthLabel }} unless noted</p></div>
    <form method="GET" action="{{ route('admin.reports.appointments') }}" class="row-flex">
        <label class="small muted" for="from">Export appointments</label>
        <input class="input" type="date" id="from" name="from" value="{{ today()->startOfMonth()->toDateString() }}" style="width:auto">
        <label class="sr-only" for="to">to</label>
        <input class="input" type="date" id="to" name="to" value="{{ today()->endOfMonth()->toDateString() }}" style="width:auto">
        <button class="btn">Download CSV</button>
    </form>
</div>

<div class="kpis">
    <div class="panel kpi"><span class="label">Appointments today</span><b class="num">{{ $todayCount }}</b></div>
    <div class="panel kpi"><span class="label">Waiting for approval</span><b class="num">{{ $pendingCount }}</b><a class="small" href="{{ route('staff.appointments.index') }}">Review requests</a></div>
    <div class="panel kpi"><span class="label">No-show rate</span><b class="num">{{ $noShowRate }}%</b><span class="small muted">{{ $noShows }} of {{ $pastCount }} past appointments</span></div>
    <div class="panel kpi"><span class="label">Fees collected</span><b class="num">₱{{ number_format($feesThisMonth) }}</b><span class="small muted">{{ $notarizedThisMonth }} documents notarized</span></div>
</div>

<div class="grid2">
    <div class="panel"><h2>Bookings per month</h2>
        @php($max = max(1, $months->max('value')))
        <div class="cols">@foreach ($months as $m)<div><span class="num">{{ $m['value'] }}</span><i style="height:{{ $m['value'] / $max * 100 }}%"></i></div>@endforeach</div>
        <div class="cols-labels">@foreach ($months as $m)<span>{{ $m['label'] }}</span>@endforeach</div>
    </div>
    <div class="panel"><h2>Bookings by practice area</h2>
        @php($max = max(1, $byService->max('value')))
        <div class="bars">@foreach ($byService as $row)
            <div class="bar"><span>{{ $row['label'] }}</span><div class="track"><div class="fill" style="width:{{ $row['value'] / $max * 100 }}%"></div></div><span class="v num">{{ $row['value'] }}</span></div>
        @endforeach</div>
    </div>
</div>

<div class="panel" style="margin-bottom:20px"><h2>Lawyer workload</h2>
    <div class="scroll"><table>
        <thead><tr><th>Lawyer</th><th>Upcoming</th><th>Completed (month)</th><th>No-show (month)</th><th>Fees (month)</th><th>Next 7 days booked</th></tr></thead>
        <tbody>@foreach ($workload as $row)
            <tr><td><b>{{ $row['lawyer']->name }}</b></td><td class="num">{{ $row['upcoming'] }}</td><td class="num">{{ $row['completed'] }}</td><td class="num">{{ $row['no_show'] }}</td>
                <td class="num">₱{{ number_format($row['fees']) }}</td>
                <td><div class="util"><div class="track"><div class="fill" style="width:{{ min(100, $row['utilization']) }}%"></div></div><span class="num small">{{ $row['utilization'] }}%</span></div></td></tr>
        @endforeach</tbody>
    </table></div>
</div>

<div class="grid2">
    <div class="panel"><h2>Bookings by status</h2>
        @php($max = max(1, $byStatus->max('value')))
        <div class="bars">@foreach ($byStatus as $row)
            <div class="bar"><span><x-status :status="$row['status']" /></span><div class="track"><div class="fill" style="width:{{ $row['value'] / $max * 100 }}%;background:var(--muted)"></div></div><span class="v num">{{ $row['value'] }}</span></div>
        @endforeach</div>
    </div>
    <div class="panel"><div class="row-flex between" style="margin-bottom:14px"><h2>Recent activity</h2><a class="small" href="{{ route('admin.audit') }}">Full audit log</a></div>
        <ul class="log">@foreach ($recentLogs as $log)
            <li><time class="num">{{ $log->created_at->format('M j, g:i A') }}</time><span><b>{{ $log->user?->name ?? 'System' }}</b> · {{ $log->description }}</span></li>
        @endforeach</ul>
    </div>
</div>
@endsection
