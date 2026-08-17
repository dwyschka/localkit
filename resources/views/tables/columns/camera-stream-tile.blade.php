@php($streams = $streams ?? [])
@if(count($streams) > 0)
    <div class="space-y-2">
        @foreach($streams as $url)
            <img
                src="{{ $url }}"
                alt="Camera snapshot"
                style="width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border: 0;"
                class="rounded-lg shadow-lg"
            />
        @endforeach
    </div>
@endif
