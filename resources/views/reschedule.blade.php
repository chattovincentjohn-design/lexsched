@extends('layouts.app')
@section('title', 'Reschedule '.$appointment->reference)
@section('content')
<p style="margin-bottom:14px"><a href="{{ $back }}">← Back</a></p>
<div class="pagehead">
    <div><h1>Move {{ $appointment->reference }}</h1>
        <p>{{ $appointment->client_name }} · {{ $appointment->service->name }}{{ $appointment->notarialDocument ? ' – '.$appointment->notarialDocument->name : '' }}. Now: <b>{{ $appointment->scheduleLabel() }}</b> with {{ $appointment->lawyer->name }}.</p></div>
</div>
@unless ($byStaff)
    <div class="alert" style="background:var(--surface-2)">The new schedule goes back to the secretary for approval. You can move an appointment online up to {{ config('lexsched.reschedule_cutoff_hours') }} hours before it starts, {{ config('lexsched.max_client_reschedules') }} times at most ({{ $appointment->reschedule_count }} used).</div>
@endunless

<form id="reschedule" method="POST" action="{{ $action }}" data-availability="{{ route('availability') }}" data-service="{{ $appointment->service_id }}" data-lawyer="{{ old('lawyer_id', $appointment->lawyer_id) }}">
    @csrf @method('PUT')
    <input type="hidden" name="date" id="f-date" value="{{ old('date') }}">
    <input type="hidden" name="time" id="f-time" value="{{ old('time') }}">
    <div class="book-grid">
        <div class="stack">
            <x-field name="lawyer_id" :label="$appointment->isNotarial() ? 'Notary public' : 'Lawyer'">
                <select id="lawyer_id" name="lawyer_id"><option value="{{ $appointment->lawyer_id }}">{{ $appointment->lawyer->name }}</option></select>
            </x-field>
        </div>
        <div class="panel">
            <div class="cal-head"><button type="button" id="cal-prev" aria-label="Previous month">‹</button><h3 id="cal-title"></h3><button type="button" id="cal-next" aria-label="Next month">›</button></div>
            <div class="cal" id="cal"></div>
        </div>
        <div class="panel">
            <h3 id="slots-title" style="margin-bottom:12px">Open times</h3>
            <div id="slots"><p class="muted small">Pick a date to see times.</p></div>
            @error('time')<p class="error" style="margin-top:8px">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="wiz-foot"><a class="btn" href="{{ $back }}">Keep current schedule</a><button class="btn primary">Move appointment</button></div>
</form>
@endsection
