@extends('layouts.site')

@section('content')
<section class="band">
    <div class="wrap inset">
        <h1>Everything DueBook does</h1>
        <p class="intro" style="margin-top:18px">One system for the money that comes back on a date, and for the rest of your money too.</p>
        <ul class="entries">
            @foreach (config('site.features') as [$icon, $feature, $text])
                <li><x-icon :name="$icon" /><h2 style="font-size:1.2rem;margin:0;letter-spacing:-.01em">{{ $feature }}</h2><p>{{ $text }}</p></li>
            @endforeach
        </ul>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="flow">
    <div class="wrap inset">
        <h2 id="flow">From a new client to a paid renewal</h2>
        <p class="intro">The same three steps every time.</p>
        <ol class="steps">
            @foreach (config('site.steps') as [$step, $text])
                <li><h3>{{ $step }}</h3><p>{{ $text }}</p></li>
            @endforeach
        </ol>
    </div>
</section>

@include('site._cta', ['heading' => 'See it with your own clients.'])
@endsection
