<nav class="subnav" aria-label="Secretary">
    <a href="{{ route('staff.appointments.index') }}" @class(['on' => request()->routeIs('staff.appointments.*')])>Appointments</a>
    <a href="{{ route('staff.walk-in.create') }}" @class(['on' => request()->routeIs('staff.walk-in.*')])>Add walk-in</a>
    <a href="{{ route('staff.register') }}" @class(['on' => request()->routeIs('staff.register*')])>Notarial register</a>
</nav>
