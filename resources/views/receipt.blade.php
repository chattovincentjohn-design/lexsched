<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $appointment->reference }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;600&family=IBM+Plex+Serif:wght@600&display=swap">
    <style>
        :root { --ink:#1a2130; --muted:#5b6476; --line:#c9ced8; --accent:#7a2331; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eceef3; color: var(--ink); font: 14px/1.5 "IBM Plex Sans", system-ui, sans-serif; }
        .toolbar { max-width: 720px; margin: 20px auto 0; padding: 0 16px; display: flex; justify-content: space-between; gap: 10px; }
        .toolbar a, .toolbar button { font: inherit; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
        .toolbar button { background: var(--accent); border-color: var(--accent); color: #fff; }
        .receipt { max-width: 720px; margin: 16px auto 40px; background: #fff; padding: 40px 44px; border: 1px solid var(--line); }
        header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--ink); padding-bottom: 16px; }
        h1 { font: 600 20px "IBM Plex Serif", Georgia, serif; margin: 0; }
        h2 { font: 600 13px "IBM Plex Sans", sans-serif; letter-spacing: .12em; text-transform: uppercase; margin: 22px 0 4px; }
        .muted { color: var(--muted); }
        .mono { font-family: "IBM Plex Mono", monospace; }
        .meta { text-align: right; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td { padding: 9px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        td:first-child { color: var(--muted); width: 38%; }
        .amount { display: flex; justify-content: space-between; align-items: baseline; margin-top: 18px; padding: 14px 16px; background: #f4f5f8; }
        .amount b { font: 600 26px "IBM Plex Serif", Georgia, serif; }
        .words { font-style: italic; margin-top: 6px; }
        .sign { display: flex; justify-content: flex-end; margin-top: 48px; }
        .sign div { width: 240px; border-top: 1px solid var(--ink); text-align: center; padding-top: 6px; font-size: 12px; }
        .note { margin-top: 28px; font-size: 11.5px; color: var(--muted); }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { border: 0; margin: 0; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="{{ url()->previous() }}">← Back</a>
    <button type="button" onclick="window.print()">Print or save as PDF</button>
</div>
<main class="receipt">
    <header>
        <div>
            <h1>{{ config('lexsched.office_name') }}</h1>
            <div class="muted">{{ config('lexsched.office_address') }}<br>{{ config('lexsched.office_phone') }}</div>
        </div>
        <div class="meta">
            <div><b>Acknowledgment Receipt</b></div>
            <div class="mono">AR-{{ $appointment->reference }}</div>
            <div>{{ $appointment->paid_at->format('F j, Y') }}</div>
        </div>
    </header>

    <h2>Received from</h2>
    <div><b>{{ $appointment->client_name }}</b>@if ($appointment->client_phone) · {{ $appointment->client_phone }}@endif</div>

    <table>
        <tr><td>For</td><td>{{ $appointment->service->name }}{{ $appointment->notarialDocument ? ' – '.$appointment->notarialDocument->name.' ('.$appointment->copies.' '.Str::plural('copy', $appointment->copies).')' : ' consultation' }}</td></tr>
        <tr><td>Appointment</td><td>{{ $appointment->reference }} · {{ $appointment->scheduleLabel() }}</td></tr>
        <tr><td>{{ $appointment->isNotarial() ? 'Notary public' : 'Lawyer' }}</td><td>{{ $appointment->lawyer->name }}</td></tr>
        <tr><td>Payment method</td><td>{{ \App\Models\Appointment::PAYMENT_METHODS[$appointment->payment_method] ?? $appointment->payment_method }}{{ $appointment->wasPaidOnline() ? ' via PayMongo' : '' }}</td></tr>
        @if ($appointment->payment_reference)<tr><td>Payment reference</td><td class="mono">{{ $appointment->payment_reference }}</td></tr>@endif
        @if ($appointment->official_receipt_no)<tr><td>Official receipt no.</td><td class="mono">{{ $appointment->official_receipt_no }}</td></tr>@endif
        @if ($appointment->notarialEntry)<tr><td>Notarial register</td><td class="mono">{{ $appointment->notarialEntry->certificateLine() }}</td></tr>@endif
    </table>

    <div class="amount"><span>Amount received</span><b>₱{{ number_format($appointment->fee, 2) }}</b></div>
    <div class="words">{{ $amountInWords }}</div>

    <div class="sign"><div>Authorized representative</div></div>

    <p class="note">This acknowledgment receipt confirms payment received through LexSched. It is not an official receipt; the office issues the BIR official receipt separately{{ $appointment->official_receipt_no ? ' (OR No. '.$appointment->official_receipt_no.')' : '' }}.</p>
</main>
</body>
</html>
