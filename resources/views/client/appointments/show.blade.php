@extends('layouts.app')
@section('title', $appointment->reference)
@section('content')
<p style="margin-bottom:14px"><a href="{{ route('client.appointments.index') }}">← My appointments</a></p>

<div class="split">
    <div class="panel stack">
        @if (session('booked'))
            <span class="chip st-pending">Request received</span>
            <h1>Your request {{ $appointment->reference }} was sent to the office</h1>
            <p class="muted">The secretary checks the schedule and for conflicts of interest, then confirms within one business day. We sent a text to {{ $appointment->client_phone }}.</p>
        @else
            <div class="row-flex between"><span class="mono muted">{{ $appointment->reference }}</span><x-status :status="$appointment->status" /></div>
            <h1>{{ $appointment->service->name }}</h1>
        @endif

        <dl class="kv">
            <dt>Service</dt><dd>{{ $appointment->service->name }}</dd>
            @if ($appointment->notarialDocument)
                <dt>Document</dt><dd>{{ $appointment->notarialDocument->name }} · {{ $appointment->copies }} {{ Str::plural('copy', $appointment->copies) }}</dd>
            @endif
            <dt>{{ $appointment->isNotarial() ? 'Notary public' : 'Lawyer' }}</dt><dd>{{ $appointment->lawyer->name }}</dd>
            <dt>Schedule</dt><dd>{{ $appointment->scheduleLabel() }}</dd>
            <dt>Mode</dt><dd>{{ $appointment->modeLabel() }}</dd>
            @if ($appointment->videoLink() && $appointment->status === \App\Enums\AppointmentStatus::Approved)
                <dt>Video link</dt><dd><a href="{{ $appointment->videoLink() }}" target="_blank" rel="noopener">{{ $appointment->videoLink() }}</a></dd>
            @endif
            <dt>Payment</dt><dd>{{ $appointment->paymentLabel() }} · ₱{{ number_format($appointment->fee, 2) }}@if ($appointment->payment_reference)<br><span class="small muted">Ref. {{ $appointment->payment_reference }}</span>@endif</dd>
            @if ($appointment->opposing_party)<dt>Opposing party</dt><dd>{{ $appointment->opposing_party }}</dd>@endif
            @if ($appointment->concern)<dt>{{ $appointment->isNotarial() ? 'Notes' : 'Concern' }}</dt><dd>{{ $appointment->concern }}</dd>@endif
            @foreach ($appointment->attachments as $attachment)
                <dt>Attachment</dt><dd><a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a></dd>
            @endforeach
            @if ($appointment->decline_reason)<dt>Reason declined</dt><dd>{{ $appointment->decline_reason }}</dd>@endif
        </dl>

        @if ($appointment->notarialDocument && $appointment->status->isCancellable())
            @include('partials.requirements', ['document' => $appointment->notarialDocument])
        @endif

        @if ($appointment->notarialEntry)
            <div class="cert"><div class="label">Notarial register entry</div><p class="mono">{{ $appointment->notarialEntry->certificateLine() }}</p></div>
        @endif
    </div>

    <aside class="panel stack">
        <h2>What happens next</h2>
        @switch($appointment->status)
            @case(\App\Enums\AppointmentStatus::Pending)
                <p>The secretary reviews your request. Once it's approved you'll get a confirmation text{{ $appointment->client_email ? ' and email' : '' }}.</p>
                @break
            @case(\App\Enums\AppointmentStatus::Approved)
                <p>You're confirmed. We'll text you a reminder the day before. Please arrive 10 minutes early{{ $appointment->mode === 'video' ? ' or join the video link on time' : '' }}.</p>
                @break
            @case(\App\Enums\AppointmentStatus::Completed)
                <p>This consultation is complete. Thank you for trusting the office.</p>
                @break
            @default
                <p>This appointment is closed. You can book a new schedule anytime.</p>
        @endswitch
        <p class="small muted">{{ config('lexsched.office_address') }} · {{ config('lexsched.office_phone') }}</p>
        @can('cancel', $appointment)
            <form method="POST" action="{{ route('client.appointments.cancel', $appointment) }}" data-confirm="Cancel {{ $appointment->reference }}? The time slot will be given to other clients.">
                @csrf
                <button class="btn danger block">Cancel this appointment</button>
            </form>
        @endcan
    </aside>
</div>
@endsection
