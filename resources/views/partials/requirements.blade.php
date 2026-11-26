<div class="reqs"><div><div class="label" style="margin-bottom:6px">Bring on your visit</div>
    <ul>@foreach ($document->requirements as $requirement)<li>{{ $requirement }}</li>@endforeach</ul></div>
    <div class="appear"><b>Personal appearance is required</b> with a valid government ID.</div>
</div>
