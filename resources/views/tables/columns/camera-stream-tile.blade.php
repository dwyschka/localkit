@php($streams = $streams ?? [])
@if(count($streams) > 0)
    <div class="space-y-2">
        @foreach($streams as $url)
            <iframe
                src="{{ $url }}"
                style="width: 100%; aspect-ratio: 16 / 9; border: 0;"
                allow="autoplay; encrypted-media"
                allowfullscreen
                class="rounded-lg shadow-lg"
            ></iframe>
        @endforeach
    </div>
@endif
