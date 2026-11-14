@extends('layouts.site')

@section('content')
<section class="band">
    <div class="wrap inset">
        <h1>Who DueBook is for</h1>
        <p class="intro" style="margin-top:18px">If a customer pays you again on a date, month after month or year after year, DueBook keeps that date so you do not have to.</p>
        <div class="who">
            @foreach (config('site.audiences') as [$id, $audience, $text])
                <article id="{{ $id }}"><h2 style="font-size:1.3rem">{{ $audience }}</h2><p>{{ $text }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="band band-sheet" aria-labelledby="before">
    <div class="wrap inset">
        <h2 id="before">The problem it removes</h2>
        <div class="prose">
            <p>Most small businesses keep renewals in their head, in old contracts, or spread across control panels. A client's hosting runs out and nobody noticed. A yearly support fee is collected three months late, or never. Money lent to someone is remembered only when it is needed.</p>
            <p>DueBook replaces that with one list sorted by due date, an email before each date, and a record of every payment. You open it on your phone, see who is late, and send the client a reminder from the same screen.</p>
        </div>
    </div>
</section>

@include('site._cta')
@endsection
