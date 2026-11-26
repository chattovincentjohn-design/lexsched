@extends('layouts.app')
@section('title', 'Book a legal consultation')
@section('content')
<section class="hero">
    <div>
        <h1>Book a legal consultation without the phone tag</h1>
        <p>Pick a lawyer and an open hour, describe your concern, and get a text when the office confirms. Notarization is by appointment too.</p>
        <div class="row-flex">
            <a class="btn primary" href="{{ auth()->check() ? route('dashboard') : route('register') }}">Book a consultation</a>
            <a class="btn" href="#services">See services and fees</a>
        </div>
    </div>
    <div class="hero-card stack">
        <div class="label">Office hours</div>
        <p><b>Monday to Friday, 9:00 AM – 5:00 PM</b><br><span class="muted small">Lunch break 12:00 – 1:00 PM · Closed on holidays</span></p>
        <p class="small">{{ config('lexsched.office_address') }}<br>{{ config('lexsched.office_phone') }}</p>
    </div>
</section>

<section id="services" class="stack" style="margin-bottom:28px">
    <h2>Services</h2>
    <div class="services">
        @foreach ($services as $service)
            <a class="svc" href="{{ auth()->check() ? route('client.book', ['service' => $service->id]) : route('register') }}">
                <b>{{ $service->name }}</b>
                <span class="muted small">{{ $service->description }}</span>
                <span class="fee"><span class="muted">{{ $service->is_notarial ? 'Per document' : '1-hour consultation' }}</span>
                    <b class="num">{{ $service->is_notarial ? 'from ₱'.number_format(\App\Models\NotarialDocument::active()->min('fee')) : '₱'.number_format($service->fee) }}</b></span>
            </a>
        @endforeach
    </div>
</section>

<section class="stack">
    <h2>Our lawyers</h2>
    <div class="lawyer-cards">
        @foreach ($lawyers as $lawyer)
            <div class="panel row-flex" style="align-items:flex-start;flex-wrap:nowrap">
                <span class="avatar">{{ collect(explode(' ', $lawyer->user->name))->map(fn ($w) => $w[0])->take(2)->implode('') }}</span>
                <div>
                    <b>{{ $lawyer->name }}</b>
                    <p class="small muted">{{ $lawyer->services->pluck('name')->implode(' · ') }}</p>
                    @if ($lawyer->years_of_practice)<p class="small muted">{{ $lawyer->years_of_practice }} years of practice</p>@endif
                    @if ($lawyer->hasValidNotarialCommission())<p class="small">Notary public</p>@endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
