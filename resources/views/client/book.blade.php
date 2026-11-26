@extends('layouts.app')
@section('title', 'Book a consultation')
@section('content')
<div class="pagehead">
    <div><h1>Book a consultation</h1><p>Good day, {{ auth()->user()->firstName() }}. Your request goes to the secretary, who confirms within one business day.</p></div>
</div>

@if ($errors->any())
    <div class="alert bad" role="alert">Please check the highlighted fields.</div>
@endif

<form id="booking" method="POST" action="{{ route('client.book.store') }}" enctype="multipart/form-data"
      data-availability="{{ route('availability') }}"
      data-copy-fee="{{ config('lexsched.extra_copy_fee') }}"
      data-old='@json(old() ?: ['service_id' => $selectedService])'
      data-errors='@json($errors->keys())'>
    @csrf
    <input type="hidden" name="date" id="f-date" value="{{ old('date') }}">
    <input type="hidden" name="time" id="f-time" value="{{ old('time') }}">

    <div class="steps" id="steps">
        <div><i>Step 1</i><span>Service</span></div>
        <div><i>Step 2</i><span>Lawyer &amp; schedule</span></div>
        <div><i>Step 3</i><span>Case details</span></div>
        <div><i>Step 4</i><span>Confirm &amp; pay</span></div>
    </div>

    {{-- Step 1: service --}}
    <section data-step="1">
        <div class="services">
            @foreach ($services as $service)
                <label class="svc">
                    <input type="radio" name="service_id" value="{{ $service->id }}" data-notarial="{{ $service->is_notarial ? 1 : 0 }}" data-fee="{{ (float) $service->fee }}" data-name="{{ $service->name }}">
                    <b>{{ $service->name }}</b>
                    <span class="muted small">{{ $service->description }}</span>
                    <span class="fee"><span class="muted">{{ $service->is_notarial ? 'Per document · 30 min' : '1-hour consultation' }}</span>
                        <b class="num">{{ $service->is_notarial ? 'from ₱'.number_format($documents->min('fee')) : '₱'.number_format($service->fee) }}</b></span>
                </label>
            @endforeach
        </div>
        @error('service_id')<p class="error" style="margin-top:8px">{{ $message }}</p>@enderror
    </section>

    {{-- Step 2: lawyer, date and time --}}
    <section data-step="2" hidden>
        <div class="book-grid">
            <div class="stack">
                <div class="label">Choose a lawyer</div>
                <div id="lawyers" class="stack"><p class="muted small">Loading lawyers…</p></div>
                @error('lawyer_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="panel">
                <div class="cal-head">
                    <button type="button" id="cal-prev" aria-label="Previous month">‹</button>
                    <h3 id="cal-title"></h3>
                    <button type="button" id="cal-next" aria-label="Next month">›</button>
                </div>
                <div class="cal" id="cal"></div>
                <p class="hint" style="margin-top:10px">Crossed-out dates are weekends, holidays, hearings, leave or fully booked.</p>
                @error('date')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="panel">
                <h3 style="margin-bottom:12px" id="slots-title">Open times</h3>
                <div id="slots"><p class="muted small">Pick a date to see times.</p></div>
                <p class="hint" style="margin-top:10px">12:00–1:00 PM is the office lunch break.</p>
                @error('time')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- Step 3: details --}}
    <section data-step="3" hidden>
        <div class="panel">
            <div class="form-grid">
                <div data-only="notarial" class="full form-grid" style="grid-column:1/-1">
                    <x-field name="notarial_document_id" label="Document to notarize">
                        <select id="notarial_document_id" name="notarial_document_id">
                            @foreach ($documents as $document)
                                <option value="{{ $document->id }}" data-fee="{{ (float) $document->fee }}" data-reqs='@json($document->requirements)' @selected(old('notarial_document_id') == $document->id)>{{ $document->name }} · ₱{{ number_format($document->fee) }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="copies" label="Number of copies" :hint="'First copy included. ₱'.config('lexsched.extra_copy_fee').' for each extra copy.'">
                        <input id="copies" name="copies" type="number" min="1" max="10" value="{{ old('copies', 1) }}">
                    </x-field>
                    <div class="reqs full">
                        <div><div class="label" style="margin-bottom:6px">Bring on your visit</div><ul id="reqs"></ul></div>
                        <div class="appear"><b>Personal appearance is required.</b> Everyone signing must appear before the notary public with a valid government ID. The notary cannot notarize for someone who is not present.</div>
                    </div>
                </div>

                <x-field name="concern" label="What is your legal concern?" class="full">
                    <textarea id="concern" name="concern" maxlength="2000" placeholder="Briefly describe what happened and what you need help with.">{{ old('concern') }}</textarea>
                </x-field>

                <div data-only="regular" class="full form-grid" style="grid-column:1/-1">
                    <x-field name="opposing_party" label="Opposing party (if any)" hint="The person or company on the other side. We use it to check for conflicts of interest.">
                        <input id="opposing_party" name="opposing_party" value="{{ old('opposing_party') }}" maxlength="150">
                    </x-field>
                    <div class="field">
                        <span class="lbl">Consultation mode</span>
                        <div class="radio-row">
                            <label><input type="radio" name="mode" value="in_office" @checked(old('mode', 'in_office') === 'in_office')> In-office</label>
                            <label><input type="radio" name="mode" value="video" @checked(old('mode') === 'video')> Video call</label>
                        </div>
                    </div>
                </div>

                <x-field name="attachment" label="Supporting document (optional)" hint="PDF, JPG, PNG or DOCX, up to 5 MB. IDs, contracts, demand letters, subpoenas or a draft of the document.">
                    <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.docx">
                </x-field>
                <div data-only="notarial" class="field">
                    <span class="lbl">Mode</span>
                    <div><span class="tag" style="font-size:13px;padding:8px 12px">In-office only</span></div>
                    <span class="hint">Notarization can't be done by video call.</span>
                </div>

                <label class="consent full"><input type="checkbox" name="consent" id="consent" value="1" @checked(old('consent'))>
                    <span>I consent to the collection and processing of my personal information for this consultation, in line with the Data Privacy Act of 2012 (RA 10173).</span></label>
                @error('consent')<span class="error full">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- Step 4: review and pay --}}
    <section data-step="4" hidden>
        <div class="split">
            <div class="panel"><h2>Review your booking</h2><dl class="kv" id="summary"></dl><div id="summary-reqs" style="margin-top:16px"></div></div>
            <div class="panel stack">
                <h2>Payment</h2>
                <div class="radio-row" style="flex-direction:column">
                    @foreach (['gcash' => 'GCash', 'maya' => 'Maya', 'office' => 'Pay at the office'] as $value => $label)
                        <label><input type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method', 'gcash') === $value)> {{ $label }}</label>
                    @endforeach
                </div>
                @foreach (config('lexsched.ewallets') as $key => $wallet)
                    <div class="wallet" data-wallet="{{ $key }}" hidden>
                        Send the fee to <b>{{ $wallet['name'] }} {{ $wallet['account'] }}</b> ({{ $wallet['holder'] }}), then enter the reference number from your receipt. The secretary checks it before confirming.
                    </div>
                @endforeach
                <x-field name="payment_reference" label="Reference number">
                    <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="40" inputmode="numeric" placeholder="e.g. 1009 834 567 123">
                </x-field>
                <div id="fee-lines"></div>
                <div class="total"><span class="muted" id="fee-label">Consultation fee</span><b class="num" id="fee-total">₱0</b></div>
            </div>
        </div>
    </section>

    <p class="error" id="step-error" role="alert" hidden></p>
    <div class="wiz-foot">
        <button type="button" class="btn" id="back">← Back</button>
        <button type="button" class="btn primary" id="next">Next →</button>
        <button type="submit" class="btn primary" id="submit" hidden>Submit booking request</button>
    </div>
</form>
@endsection
