@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="pagehead">
    <div><h1>Users</h1><p>Create accounts for the secretary and lawyers. Clients sign up on their own.</p></div>
    <a class="btn primary" href="{{ route('admin.users.create') }}">+ Add staff or lawyer</a>
</div>
<div class="row-flex between" style="margin-bottom:16px">
    <div class="tabs">
        <a href="{{ route('admin.users.index') }}" @class(['on' => ! $role])>All</a>
        @foreach (\App\Enums\UserRole::cases() as $case)
            <a href="{{ route('admin.users.index', ['role' => $case->value]) }}" @class(['on' => $role === $case])>{{ $case->label() }}</a>
        @endforeach
    </div>
    <form method="GET" class="row-flex"><input type="hidden" name="role" value="{{ $role?->value }}">
        <label class="sr-only" for="q">Search</label><input class="input" id="q" name="q" value="{{ $search }}" placeholder="Search name or email" style="width:220px"><button class="btn">Search</button></form>
</div>
<div class="panel" style="padding:6px 0"><div class="scroll"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Details</th><th>Status</th><th></th></tr></thead>
    <tbody>@foreach ($users as $user)
        <tr><td><b>{{ $user->name }}</b></td><td>{{ $user->email }}</td><td class="num">{{ $user->phone }}</td><td>{{ $user->role->label() }}</td>
            <td class="small muted">@if ($user->lawyer){{ $user->lawyer->services->pluck('name')->implode(', ') }}@if ($user->lawyer->notarial_commission_no)<br>Notary · {{ $user->lawyer->notarial_commission_no }}@endif @endif</td>
            <td>@if ($user->is_active)<span class="chip st-completed">Active</span>@else<span class="chip st-cancelled">Disabled</span>@endif</td>
            <td><a class="btn sm" href="{{ route('admin.users.edit', $user) }}">Edit</a></td></tr>
    @endforeach</tbody>
</table></div>
    @if ($users->hasPages())<div class="pager"><span class="muted">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span><span class="row-flex">@if ($users->previousPageUrl())<a class="btn sm" href="{{ $users->previousPageUrl() }}">‹ Previous</a>@endif @if ($users->nextPageUrl())<a class="btn sm" href="{{ $users->nextPageUrl() }}">Next ›</a>@endif</span></div>@endif
</div>
@endsection
