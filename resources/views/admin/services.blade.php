@extends('layouts.app')
@section('title', 'Services & fees')
@section('content')
<div class="pagehead"><div><h1>Services &amp; fees</h1><p>New fees apply to new bookings. Existing appointments keep the fee they were booked with.</p></div></div>
@if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif

<div class="panel" style="margin-bottom:20px"><h2>Practice areas</h2>
    <div class="scroll"><table>
        <thead><tr><th>Service</th><th>Description</th><th>Lawyers</th><th>Fee (₱)</th><th>Active</th><th></th></tr></thead>
        <tbody>@foreach ($services as $service)
            @php($f = 'service-'.$service->id)
            <tr>
                <td><input form="{{ $f }}" class="input" name="name" value="{{ $service->name }}" aria-label="Name" style="min-width:150px"></td>
                <td><input form="{{ $f }}" class="input" name="description" value="{{ $service->description }}" aria-label="Description" style="min-width:260px"></td>
                <td class="num">{{ $service->lawyers_count }}</td>
                <td>@if ($service->is_notarial)<span class="small muted">Per document, below</span><input form="{{ $f }}" type="hidden" name="fee" value="0">@else<input form="{{ $f }}" class="input fee-input num" type="number" name="fee" min="0" step="50" value="{{ (float) $service->fee }}" aria-label="Fee">@endif</td>
                <td><input form="{{ $f }}" type="checkbox" name="is_active" value="1" @checked($service->is_active) aria-label="Active"></td>
                <td><form id="{{ $f }}" method="POST" action="{{ route('admin.services.update', $service) }}">@csrf @method('PUT')<button class="btn sm">Save</button></form></td>
            </tr>
        @endforeach</tbody>
    </table></div>
</div>

<div class="panel"><h2>Notarial fee schedule</h2>
    <p class="hint" style="margin-bottom:12px">Extra copies are ₱{{ config('lexsched.extra_copy_fee') }} each. Put one requirement per line.</p>
    <div class="scroll"><table>
        <thead><tr><th>Document</th><th>Requirements</th><th>Fee (₱)</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @foreach ($documents as $document)
            @php($f = 'document-'.$document->id)
            <tr>
                <td><input form="{{ $f }}" class="input" name="name" value="{{ $document->name }}" aria-label="Document name" style="min-width:200px"></td>
                <td><textarea form="{{ $f }}" class="input" name="requirements" rows="3" aria-label="Requirements" style="min-width:280px">{{ implode("\n", $document->requirements) }}</textarea></td>
                <td><input form="{{ $f }}" class="input fee-input num" type="number" name="fee" min="0" step="50" value="{{ (float) $document->fee }}" aria-label="Fee"></td>
                <td><input form="{{ $f }}" type="checkbox" name="is_active" value="1" @checked($document->is_active) aria-label="Active"></td>
                <td><form id="{{ $f }}" method="POST" action="{{ route('admin.documents.update', $document) }}">@csrf @method('PUT')<button class="btn sm">Save</button></form></td>
            </tr>
        @endforeach
            @php($f = 'document-new')
            <tr>
                <td><input form="{{ $f }}" class="input" name="name" placeholder="New document type" aria-label="New document name" required></td>
                <td><textarea form="{{ $f }}" class="input" name="requirements" rows="2" placeholder="1 valid government ID" aria-label="Requirements" required></textarea></td>
                <td><input form="{{ $f }}" class="input fee-input num" type="number" name="fee" min="0" step="50" value="300" aria-label="Fee"></td>
                <td><input form="{{ $f }}" type="hidden" name="is_active" value="1"></td>
                <td><form id="{{ $f }}" method="POST" action="{{ route('admin.documents.store') }}">@csrf<button class="btn sm primary">Add</button></form></td>
            </tr>
        </tbody>
    </table></div>
</div>
@endsection
