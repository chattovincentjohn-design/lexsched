<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · LexSched</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main style="min-height:100vh;display:grid;place-items:center;padding:24px 16px">
    <div class="panel stack" style="max-width:480px;width:100%;text-align:left">
        <div class="row-flex"><div class="seal" aria-hidden="true">§</div><span class="mono muted">Error @yield('code')</span></div>
        <h1>@yield('title')</h1>
        <p class="muted">@yield('message')</p>
        <div class="row-flex"><a class="btn primary" href="{{ url('/dashboard') }}">Go to my page</a><a class="btn" href="{{ url('/') }}">Home</a></div>
    </div>
</main>
</body>
</html>
