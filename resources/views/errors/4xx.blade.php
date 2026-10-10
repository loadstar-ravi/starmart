{{-- Shown for every 4xx status that has no page of its own, such as 405. --}}
<x-layouts.error :code="$exception->getStatusCode()" title="That request didn't work">
    We could not do what was asked. Go back and try again, or start over from the home page.
</x-layouts.error>
