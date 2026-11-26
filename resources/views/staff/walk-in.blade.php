@extends('layouts.app')
@section('title', 'Add walk-in')
@section('content')
@include('staff.subnav')
<div class="pagehead"><div><h1>Add walk-in client</h1><p>Walk-ins are approved right away. The slot that started within the last hour can still be used.</p></div></div>

<form id="walkin" method="POST" action="{{ route('staff.walk-in.store') }}" data-availability="{{ route('availability') }}" data-today="{{ today()->toDateString() }}">
    @csrf
    <input type="hidden" name="date" id="f-date" value="{{ old('date', request('date')) }}">
    <input type="hidden" name="time" id="f-time" value="{{ old('time', request('time')) }}">
    <div class="split">
        <div class="panel form-grid" style="align-self:start">
            <x-field name="client_name" label="Client name" class="full"><input id="client_name" name="client_name" value="{{ old('client_name') }}" required maxlength="120"></x-field>
            <x-field name="client_phone" label="Mobile number" hint="For the confirmation and reminder texts."><input id="client_phone" name="client_phone" value="{{ old('client_phone') }}" placeholder="09171234567"></x-field>
            <x-field name="client_email" label="Email (optional)"><input id="client_email" name="client_email" type="email" value="{{ old('client_email') }}"></x-field>
            <x-field name="service_id" label="Service">
                <select id="service_id" name="service_id">
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" data-notarial="{{ $service->is_notarial ? 1 : 0 }}" @selected(old('service_id', request('lawyer') ? $services->first(fn ($s) => $s->lawyers()->whereKey(request('lawyer'))->exists())?->id : $services->firstWhere('is_notarial')?->id) == $service->id)>{{ $service->name }}{{ $service->is_notarial ? '' : ' · ₱'.number_format($service->fee) }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="lawyer_id" label="Lawyer"><select id="lawyer_id" name="lawyer_id" data-old="{{ old('lawyer_id', request('lawyer')) }}"><option value="any">First available</option></select></x-field>
            <div data-only="notarial" class="full form-grid" style="grid-column:1/-1">
                <x-field name="notarial_document_id" label="Document">
                    <select id="notarial_document_id" name="notarial_document_id">
                        @foreach ($documents as $document)<option value="{{ $document->id }}" @selected(old('notarial_document_id') == $document->id)>{{ $document->name }} · ₱{{ number_format($document->fee) }}</option>@endforeach
                    </select>
                </x-field>
                <x-field name="copies" label="Copies"><input id="copies" name="copies" type="number" min="1" max="10" value="{{ old('copies', 1) }}"></x-field>
            </div>
            <x-field name="opposing_party" label="Opposing party (if any)" class="full"><input id="opposing_party" name="opposing_party" value="{{ old('opposing_party') }}" maxlength="150"></x-field>
            <x-field name="concern" label="Concern or notes" class="full"><textarea id="concern" name="concern" maxlength="2000">{{ old('concern') }}</textarea></x-field>
        </div>
        <div class="stack">
            <div class="panel">
                <div class="cal-head"><button type="button" id="cal-prev" aria-label="Previous month">‹</button><h3 id="cal-title"></h3><button type="button" id="cal-next" aria-label="Next month">›</button></div>
                <div class="cal" id="cal"></div>
                @error('date')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="panel">
                <h3 id="slots-title" style="margin-bottom:12px">Open times</h3>
                <div id="slots"></div>
                @error('time')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn primary block">Add to schedule</button>
        </div>
    </div>
</form>
@endsection
