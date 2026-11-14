@extends('layouts.site', ['faqSchema' => $faqs])

@section('content')
<section class="band">
    <div class="wrap inset">
        <h1>Questions and answers</h1>
        <p class="intro" style="margin-top:18px">Short answers first. If yours is not here, <a href="{{ route('site.contact') }}">write to us</a>.</p>
        @include('site._faq', ['items' => $faqs])
    </div>
</section>

@include('site._cta')
@endsection
