@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
<div class="pagehead"><div><h1>Audit log</h1><p>Every booking, approval, payment, notarization and settings change, newest first.</p></div></div>
<div class="panel" style="padding:6px 0"><div class="scroll"><table>
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead>
    <tbody>@foreach ($logs as $log)
        <tr><td class="num" style="white-space:nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td><span class="tag">{{ str_replace('_', ' ', $log->action) }}</span></td><td>{{ $log->description }}</td><td class="mono">{{ $log->ip_address }}</td></tr>
    @endforeach</tbody>
</table></div>
    @if ($logs->hasPages())<div class="pager"><span class="muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span><span class="row-flex">@if ($logs->previousPageUrl())<a class="btn sm" href="{{ $logs->previousPageUrl() }}">‹ Newer</a>@endif @if ($logs->nextPageUrl())<a class="btn sm" href="{{ $logs->nextPageUrl() }}">Older ›</a>@endif</span></div>@endif
</div>
@endsection
