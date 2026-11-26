@php($status = $appointment->status)
@php($S = \App\Enums\AppointmentStatus::class)
<aside class="panel detail stack">
    <div class="row-flex between"><span class="mono muted">{{ $appointment->reference }}</span><x-status :status="$status" /></div>
    <div><h2>{{ $appointment->client_name }}</h2><p class="muted small">{{ $appointment->source === 'walk_in' ? 'Walk-in' : 'Online booking' }} · {{ $appointment->client_phone ?: 'no mobile number' }}{{ $appointment->client_email ? ' · '.$appointment->client_email : '' }}</p></div>

    <dl class="kv">
        <dt>Service</dt><dd>{{ $appointment->service->name }}</dd>
        @if ($appointment->notarialDocument)
            <dt>Document</dt><dd>{{ $appointment->notarialDocument->name }} · {{ $appointment->copies }} {{ Str::plural('copy', $appointment->copies) }}</dd>
        @endif
        <dt>{{ $appointment->isNotarial() ? 'Notary public' : 'Lawyer' }}</dt><dd>{{ $appointment->lawyer->name }}</dd>
        <dt>Schedule</dt><dd>{{ $appointment->scheduleLabel() }}</dd>
        <dt>Mode</dt><dd>{{ $appointment->modeLabel() }}</dd>
        <dt>Payment</dt><dd>{{ $appointment->paymentLabel() }} · ₱{{ number_format($appointment->fee, 2) }}
            @if ($appointment->wasPaidOnline())<br><span class="small muted">Confirmed by PayMongo · {{ $appointment->paymongo_payment_id }}</span>
            @elseif ($appointment->payment_reference)<br><span class="small muted">Ref. {{ $appointment->payment_reference }} (check before approving)</span>
            @elseif ($appointment->payment_method === 'online')<br><span class="small muted">Chose to pay online, not paid yet</span>@endif
            @if ($appointment->official_receipt_no)<br><span class="small muted">OR No. {{ $appointment->official_receipt_no }}</span>@endif</dd>
        @unless ($appointment->isNotarial())
            <dt>Opposing party</dt><dd>{{ $appointment->opposing_party ?: '—' }}</dd>
        @endunless
        @foreach ($appointment->attachments as $attachment)
            <dt>Attachment</dt><dd><a href="{{ route('attachments.show', $attachment) }}">{{ $attachment->original_name }}</a></dd>
        @endforeach
    </dl>

    @if ($appointment->concern)
        <div><div class="label" style="margin-bottom:6px">{{ $appointment->isNotarial() ? 'Notes' : 'Concern' }}</div><div class="concern">{{ $appointment->concern }}</div></div>
    @endif

    @if ($appointment->isNotarial())
        @if ($appointment->notarialEntry)
            <div class="cert"><div class="label">Notarial certificate entry</div>
                <p class="mono">Doc. No. {{ $appointment->notarialEntry->doc_no }}; Page No. {{ $appointment->notarialEntry->page_no }};<br>Book No. {{ $appointment->notarialEntry->book_no }}; Series of {{ $appointment->notarialEntry->series }}</p>
                <p class="small muted">ID presented: {{ $appointment->notarialEntry->id_type }}</p></div>
        @elseif ($status === $S::Approved)
            <form method="POST" action="{{ route('staff.appointments.checklist', $appointment) }}" class="verify stack" style="gap:6px">
                @csrf
                <div class="label">Notarial checklist</div>
                <label class="chk"><input type="checkbox" name="check_appeared" value="1" @checked($appointment->check_appeared)> Signatory personally appeared before the notary</label>
                <label class="chk"><input type="checkbox" name="check_id_presented" value="1" @checked($appointment->check_id_presented)> Presented a valid government ID</label>
                <label class="chk"><input type="checkbox" name="check_document_complete" value="1" @checked($appointment->check_document_complete)> Document is complete, with no blank spaces</label>
                <div class="field"><label for="id_type">ID presented</label>
                    <select id="id_type" name="id_type"><option value="">Select ID type</option>
                        @foreach (config('lexsched.id_types') as $idType)<option @selected($appointment->id_type === $idType)>{{ $idType }}</option>@endforeach
                    </select></div>
                <p class="hint">Client should bring: {{ implode('; ', $appointment->notarialDocument->requirements) }}.</p>
                <button class="btn sm">Save checklist</button>
            </form>
        @endif
    @elseif ($findings)
        <div class="conflict warn"><span aria-hidden="true">⚠</span><div><b>Possible conflict of interest</b>
            @foreach ($findings as $finding){{ $finding }}<br>@endforeach Refer to the managing partner before approving.</div></div>
    @else
        <div class="conflict ok"><span aria-hidden="true">✓</span><div><b>No conflict found</b>Checked the client and opposing party against {{ $clientCount }} client records.</div></div>
    @endif

    <div class="row-flex">
        @if ($status === $S::Pending)
            <form method="POST" action="{{ route('staff.appointments.approve', $appointment) }}" @if ($findings) data-confirm="This request has a possible conflict of interest. Approve anyway?" @endif>@csrf<button class="btn primary">Approve</button></form>
        @endif
        @if ($status === $S::Approved)
            @if ($appointment->isNotarial())
                <form method="POST" action="{{ route('staff.appointments.notarize', $appointment) }}" data-confirm="Record this document in the notarial register? This assigns the next document number and can't be undone.">@csrf
                    <button class="btn primary" @disabled(! $appointment->notarialChecklistComplete())>Notarize &amp; record</button></form>
            @else
                <form method="POST" action="{{ route('staff.appointments.complete', $appointment) }}">@csrf<button class="btn primary">Mark completed</button></form>
            @endif
            @unless ($appointment->isUpcoming())
                <form method="POST" action="{{ route('staff.appointments.no-show', $appointment) }}">@csrf<button class="btn">No-show</button></form>
            @endunless
            <form method="POST" action="{{ route('staff.appointments.cancel', $appointment) }}" data-confirm="Cancel {{ $appointment->reference }} and notify the client?{{ $appointment->wasPaidOnline() ? ' This was paid online: refund it from the PayMongo dashboard.' : '' }}">@csrf<button class="btn danger">Cancel</button></form>
        @endif
    </div>

    @if ($status === $S::Pending)
        <details class="more">
            <summary>Decline with a reason</summary>
            <form method="POST" action="{{ route('staff.appointments.decline', $appointment) }}" class="stack" style="margin-top:10px">
                @csrf
                <input class="input" name="reason" maxlength="200" placeholder="e.g. Conflict of interest, the lawyer is unavailable">
                <button class="btn danger">Decline request</button>
            </form>
        </details>
    @endif

    @if (! $appointment->isPaid() && ! in_array($status, [$S::Declined, $S::Cancelled], true))
        <details class="more">
            <summary>Record payment (₱{{ number_format($appointment->fee, 2) }})</summary>
            <form method="POST" action="{{ route('staff.appointments.payment', $appointment) }}" class="stack" style="margin-top:10px">
                @csrf
                <div class="radio-row">
                    <label><input type="radio" name="method" value="cash" checked> Cash</label>
                    <label><input type="radio" name="method" value="gcash"> GCash</label>
                    <label><input type="radio" name="method" value="maya"> Maya</label>
                </div>
                <input class="input" name="official_receipt_no" maxlength="30" placeholder="Official receipt no. (optional)">
                <button class="btn">Record payment</button>
            </form>
        </details>
    @endif
</aside>
