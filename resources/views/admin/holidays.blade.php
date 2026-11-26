@extends('layouts.app')
@section('title', 'Holidays')
@section('content')
<div class="pagehead">
    <div><h1>Holidays</h1><p>The office is closed on these days, so clients can't book them. Add holidays as Malacañang proclaims them, such as Eid'l Fitr or special non-working days.</p></div>
</div>

<div class="split">
    <div class="panel" style="padding:6px 0">
        <div class="scroll"><table>
            <thead><tr><th>Date</th><th>Holiday</th><th>Booked</th><th></th></tr></thead>
            <tbody>
            @forelse ($holidays as $holiday)
                @php($booked = $bookedCounts[$holiday->date->toDateString()] ?? 0)
                <tr @class(['muted' => $holiday->date->isPast() && ! $holiday->date->isToday()])>
                    <td class="num" style="white-space:nowrap">{{ $holiday->date->format('D, M j, Y') }}</td>
                    <td><b>{{ $holiday->name }}</b></td>
                    <td>@if ($booked)<a class="chip st-conflict" href="{{ route('staff.calendar', ['date' => $holiday->date->toDateString()]) }}">{{ $booked }} to move</a>@else<span class="muted">—</span>@endif</td>
                    <td><form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}" data-confirm="Remove {{ $holiday->name }}? Clients will be able to book that day again.">@csrf @method('DELETE')<button class="btn sm">Remove</button></form></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No holidays yet this year.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <form method="POST" action="{{ route('admin.holidays.store') }}" class="panel stack">
        @csrf
        <h2>Add a holiday</h2>
        <x-field name="date" label="Date"><input id="date" name="date" type="date" value="{{ old('date') }}" required></x-field>
        <x-field name="name" label="Name" hint="For example: Eid'l Fitr, or Special non-working day (city fiesta)."><input id="name" name="name" value="{{ old('name') }}" maxlength="100" required></x-field>
        <button class="btn primary">Add holiday</button>
    </form>
</div>
@endsection
