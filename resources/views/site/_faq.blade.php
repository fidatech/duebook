{{-- questions with the answer first; the same text goes to search engines as FAQ data --}}
<div class="faq">
    @foreach ($items as $index => [$question, $answer])
        <details @if ($index === 0) open @endif>
            <summary>{{ $question }}</summary>
            <p>{{ $answer }}</p>
        </details>
    @endforeach
</div>
