@extends('layouts.app')
@section('title', 'Notarial register')
@section('content')
@include('staff.subnav')
<div class="pagehead">
    <div><h1>Notarial register</h1><p>Series of {{ $series }} · {{ $totalEntries }} {{ Str::plural('entry', $totalEntries) }} · ₱{{ number_format($totalFees, 2) }} in notarial fees</p></div>
    <form method="GET" class="row-flex">
        <label class="sr-only" for="notary">Notary public</label>
        <select class="input" id="notary" name="notary" style="width:auto" onchange="this.form.submit()">
            @foreach ($notaries as $notary)<option value="{{ $notary->id }}" @selected($notary->id === $notaryId)>{{ $notary->name }}</option>@endforeach
        </select>
        <label class="sr-only" for="series">Series</label>
        <select class="input" id="series" name="series" style="width:auto" onchange="this.form.submit()">
            @foreach (range(now()->year, now()->year - 3) as $year)<option @selected($year === $series)>{{ $year }}</option>@endforeach
        </select>
        @if ($notaryId)<a class="btn" href="{{ route('staff.register.export', ['notary' => $notaryId, 'series' => $series]) }}">Export CSV</a>@endif
    </form>
</div>
<div class="panel" style="padding:6px 0">
    <div class="scroll">
        <table>
            <thead><tr><th>Doc. no.</th><th>Page</th><th>Book</th><th>Date</th><th>Document</th><th>Principal</th><th>ID presented</th><th>Fee</th></tr></thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td class="mono">{{ $entry->doc_no }}</td><td class="num">{{ $entry->page_no }}</td><td>{{ $entry->book_no }}</td>
                    <td class="num">{{ $entry->notarized_on->format('D, M j') }}</td>
                    <td>{{ $entry->document_name }}@if ($entry->copies > 1) <span class="tag">{{ $entry->copies }} copies</span>@endif</td>
                    <td><b>{{ $entry->principal_name }}</b></td><td>{{ $entry->id_type }}</td>
                    <td class="num">₱{{ number_format($entry->fee, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No documents notarized in this series yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="pager"><span class="muted">Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
            <span class="row-flex">@if ($entries->previousPageUrl())<a class="btn sm" href="{{ $entries->previousPageUrl() }}">‹ Previous</a>@endif @if ($entries->nextPageUrl())<a class="btn sm" href="{{ $entries->nextPageUrl() }}">Next ›</a>@endif</span></div>
    @endif
</div>
<p class="hint" style="margin-top:10px">Each notary public keeps a separate register. The entry is printed on the notarial certificate, for example “Doc. No. 238; Page No. 48; Book No. III; Series of {{ $series }}.”</p>
@endsection
