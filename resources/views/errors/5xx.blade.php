{{-- Shown for every 5xx status that has no page of its own, such as 502. --}}
<x-layouts.error :code="$exception->getStatusCode()" title="Something went wrong">
    The problem is on our side, not yours. Please try again in a moment.
</x-layouts.error>
